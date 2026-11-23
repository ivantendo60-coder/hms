<?php

function payment_provider_options(): array {
    return [
        'mtn' => ['label' => 'MTN Mobile Money', 'placeholder' => 'MTN phone number'],
        'airtel' => ['label' => 'Airtel Money', 'placeholder' => 'Airtel phone number'],
        'bank' => ['label' => 'Bank Transfer', 'placeholder' => 'Bank account number'],
    ];
}

function ensure_staff_payment_columns(PDO $pdo): void {
    $expected = [
        ['provider_reference', 'VARCHAR(100) NULL'],
        ['gateway_response', 'TEXT NULL'],
        ['processed_at', 'TIMESTAMP NULL'],
        ['payout_account_id', 'INT NULL'],
    ];

    foreach ($expected as [$column, $definition]) {
        try {
            $pdo->query("SELECT $column FROM staff_payments LIMIT 1");
        } catch (PDOException $e) {
            $pdo->exec("ALTER TABLE staff_payments ADD COLUMN $column $definition");
        }
    }

    try {
        $pdo->exec('ALTER TABLE staff_payments ADD INDEX idx_staff_payments_payout_account (payout_account_id)');
    } catch (PDOException $e) {
        // The index already exists or the database driver does not support duplicate reporting.
    }

    try {
        $pdo->exec('ALTER TABLE staff_payments ADD CONSTRAINT fk_staff_payments_payout_account
            FOREIGN KEY (payout_account_id) REFERENCES payout_accounts(id) ON DELETE SET NULL');
    } catch (PDOException $e) {
        // The foreign key already exists or payout_accounts is unavailable.
    }
}

function dispatch_payout_provider(PDO $pdo, array $payment): array {
    ensure_staff_payment_columns($pdo);

    $provider = $payment['provider'];
    $providerUrl = getenv(strtoupper($provider) . '_PAYOUT_API_URL');
    $payload = [
        'provider' => $provider,
        'reference' => $payment['reference'],
        'amount' => (float)$payment['amount'],
        'currency' => $payment['currency'],
        'recipient' => $payment['provider_account'],
        'staff' => $payment['staff_name'],
        'timestamp' => gmdate('c'),
    ];

    if (!empty($providerUrl)) {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode($payload),
                'timeout' => 8,
            ],
        ]);
        $response = @file_get_contents($providerUrl, false, $context);
        if ($response !== false) {
            $decoded = json_decode($response, true);
            $status = $decoded['status'] ?? 'processing';
            $message = $decoded['message'] ?? 'Provider accepted the payout request.';
            $providerReference = $decoded['reference'] ?? null;
            return ['success' => true, 'status' => $status, 'message' => $message, 'provider_reference' => $providerReference];
        }
    }

    $providerReference = 'SIM-' . strtoupper(substr($provider, 0, 3)) . '-' . bin2hex(random_bytes(4));
    return [
        'success' => true,
        'status' => 'simulated',
        'message' => 'Payout simulated locally because no live gateway URL is configured yet. Configure the provider endpoint to process real payments.',
        'provider_reference' => $providerReference,
    ];
}

function update_payment_result(PDO $pdo, int $paymentId, string $status, ?string $providerReference, ?string $message): void {
    ensure_staff_payment_columns($pdo);
    $stmt = $pdo->prepare('UPDATE staff_payments SET status=?, provider_reference=?, gateway_response=?, processed_at=NOW() WHERE id=?');
    $stmt->execute([$status, $providerReference, $message, $paymentId]);
}

function create_staff_payment(PDO $pdo, array $data, int $initiatedBy): array {
    ensure_staff_payment_columns($pdo);
    try {
        $pdo->query("SELECT payout_account_id FROM staff_payments LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec('ALTER TABLE staff_payments ADD COLUMN payout_account_id INT NULL');
    }

    $staffId = (int)($data['staff_id'] ?? 0);
    $amount = (float)($data['amount'] ?? 0);
    $provider = strtolower((string)($data['provider'] ?? ''));
    $notes = trim((string)($data['notes'] ?? ''));

    if ($staffId <= 0 || $amount <= 0 || !isset(payment_provider_options()[$provider])) {
        return ['success' => false, 'message' => 'Invalid payment request.'];
    }

    $staff = $pdo->prepare('SELECT id, name, email, phone FROM users WHERE id=?');
    $staff->execute([$staffId]);
    $staffRow = $staff->fetch();
    if (!$staffRow) {
        return ['success' => false, 'message' => 'Staff member not found.'];
    }

    $payoutAccountId = (int)($data['payout_account_id'] ?? 0);
    $providerAccount = '';
    $selectedAccount = null;
    if ($payoutAccountId > 0) {
        $account = $pdo->prepare('SELECT * FROM payout_accounts WHERE id=?');
        $account->execute([$payoutAccountId]);
        $selectedAccount = $account->fetch();
        if (!$selectedAccount) {
            return ['success' => false, 'message' => 'Selected payout account was not found.'];
        }
        if (strtolower((string)$selectedAccount['provider']) !== $provider) {
            return ['success' => false, 'message' => 'Selected payout account provider does not match the payment provider.'];
        }
    }

    if ($provider === 'bank') {
        $bankName = $selectedAccount ? trim((string)$selectedAccount['bank_name']) : trim((string)($data['bank_name'] ?? ''));
        $accountNumber = $selectedAccount ? trim((string)$selectedAccount['account_number']) : trim((string)($data['account_number'] ?? ''));
        if ($bankName === '' || $accountNumber === '') {
            return ['success' => false, 'message' => 'Bank name and account number are required.'];
        }
        $providerAccount = $bankName . '|' . $accountNumber;
    } else {
        $phone = $selectedAccount
            ? trim((string)($selectedAccount['phone_number'] ?: $selectedAccount['account_number']))
            : trim((string)($data['phone'] ?? ''));
        if ($phone === '') {
            return ['success' => false, 'message' => 'Phone number is required for mobile money payouts.'];
        }
        $providerAccount = $phone;
    }

    $stmt = $pdo->prepare('INSERT INTO staff_payments (staff_id, amount, currency, provider, provider_account, payout_account_id, initiated_by, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $staffId,
        number_format($amount, 2, '.', ''),
        'UGX',
        $provider,
        $providerAccount,
        $payoutAccountId > 0 ? $payoutAccountId : null,
        $initiatedBy,
        'queued',
        $notes,
    ]);

    $paymentId = (int)$pdo->lastInsertId();
    $reference = 'PAY-' . date('Ymd') . '-' . str_pad((string)$paymentId, 4, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE staff_payments SET reference=? WHERE id=?')->execute([$reference, $paymentId]);

    $payment = [
        'id' => $paymentId,
        'reference' => $reference,
        'staff_id' => $staffId,
        'staff_name' => $staffRow['name'],
        'amount' => number_format($amount, 2, '.', ''),
        'currency' => 'UGX',
        'provider' => $provider,
        'provider_account' => $providerAccount,
    ];

    $dispatch = dispatch_payout_provider($pdo, $payment);
    $status = $dispatch['success'] ? $dispatch['status'] : 'failed';
    update_payment_result($pdo, $paymentId, $status, $dispatch['provider_reference'] ?? null, $dispatch['message'] ?? '');

    return [
        'success' => true,
        'message' => $dispatch['message'] ?? 'Payment request queued successfully.',
        'payment' => [
            'id' => $paymentId,
            'reference' => $reference,
            'staff_id' => $staffId,
            'staff_name' => $staffRow['name'],
            'amount' => number_format($amount, 2, '.', ''),
            'provider' => $provider,
            'provider_account' => $providerAccount,
            'status' => $status,
            'currency' => 'UGX',
        ],
    ];
}

function retry_staff_payment(PDO $pdo, int $paymentId): array {
    ensure_staff_payment_columns($pdo);
    $stmt = $pdo->prepare('SELECT sp.*, u.name AS staff_name FROM staff_payments sp JOIN users u ON u.id=sp.staff_id WHERE sp.id=?');
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();
    if (!$payment) {
        return ['success' => false, 'message' => 'Payment record not found.'];
    }

    $dispatch = dispatch_payout_provider($pdo, [
        'reference' => $payment['reference'],
        'amount' => $payment['amount'],
        'currency' => $payment['currency'],
        'provider' => $payment['provider'],
        'provider_account' => $payment['provider_account'],
        'staff_name' => $payment['staff_name'],
    ]);
    $status = $dispatch['success'] ? $dispatch['status'] : 'failed';
    update_payment_result($pdo, $paymentId, $status, $dispatch['provider_reference'] ?? null, $dispatch['message'] ?? '');
    return ['success' => true, 'message' => $dispatch['message'] ?? 'Payment retried.'];
}

function get_staff_payment_history(PDO $pdo, ?int $staffId = null): array {
    $sql = 'SELECT sp.*, u.name AS staff_name, u.role, u.email, u.phone,
                   pa.account_name AS payout_account_name, pa.account_number AS payout_account_number
            FROM staff_payments sp
            JOIN users u ON u.id=sp.staff_id
            LEFT JOIN payout_accounts pa ON pa.id=sp.payout_account_id';
    $params = [];
    if ($staffId) {
        $sql .= ' WHERE sp.staff_id=?';
        $params[] = $staffId;
    }
    $sql .= ' ORDER BY sp.created_at DESC LIMIT 50';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
