<?php

require_once __DIR__ . '/audit.php';

function visit_billing_summary(PDO $pdo, int $visitId): array {
    $charges = $pdo->prepare('SELECT COALESCE(SUM(line_total), 0) FROM visit_services WHERE visit_id=?');
    $charges->execute([$visitId]);
    $payments = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM visit_payments WHERE visit_id=?');
    $payments->execute([$visitId]);
    $services = $pdo->prepare('SELECT vs.*, u.name AS staff_name FROM visit_services vs LEFT JOIN users u ON u.id=vs.recorded_by WHERE vs.visit_id=? ORDER BY vs.id');
    $services->execute([$visitId]);
    $receipts = $pdo->prepare('SELECT vp.*, u.name AS staff_name FROM visit_payments vp LEFT JOIN users u ON u.id=vp.collected_by WHERE vp.visit_id=? ORDER BY vp.id DESC');
    $receipts->execute([$visitId]);

    $totalCents = (int)round((float)$charges->fetchColumn() * 100);
    $paidCents = (int)round((float)$payments->fetchColumn() * 100);
    return [
        'total_cents' => $totalCents,
        'paid_cents' => $paidCents,
        'balance_cents' => max(0, $totalCents - $paidCents),
        'services' => $services->fetchAll(),
        'payments' => $receipts->fetchAll(),
    ];
}

function billing_amount_cents($amount): int {
    if (!is_string($amount) && !is_int($amount) && !is_float($amount)) {
        throw new InvalidArgumentException('Enter a valid payment amount.');
    }
    $amount = trim((string)$amount);
    if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $amount)) {
        throw new InvalidArgumentException('Enter a valid amount with no more than two decimal places.');
    }
    $cents = (int)round((float)$amount * 100);
    if ($cents <= 0) {
        throw new InvalidArgumentException('The amount must be greater than zero.');
    }
    return $cents;
}

function billing_positive_integer($value, string $label, int $maximum = 2147483647): int {
    if (!is_string($value) && !is_int($value)) {
        throw new InvalidArgumentException('Choose a valid ' . $label . '.');
    }
    $value = (string)$value;
    if (!preg_match('/^[1-9]\d*$/D', $value) || (float)$value > $maximum) {
        throw new InvalidArgumentException('Choose a valid ' . $label . '.');
    }
    return (int)$value;
}

function billing_string_value($value, string $label): string {
    if (!is_string($value)) {
        throw new InvalidArgumentException('Enter a valid ' . $label . '.');
    }
    return $value;
}

function format_billing_cents(int $cents): string {
    return number_format($cents / 100, 2, '.', '');
}

function invalidate_visit_clearance(PDO $pdo, int $visitId): void {
    $pdo->prepare('UPDATE visits SET pharmacy_cleared=0, pharmacy_cleared_by=NULL, pharmacy_cleared_at=NULL WHERE id=? AND pharmacy_cleared=1')
        ->execute([$visitId]);
}

function record_visit_service(PDO $pdo, int $visitId, int $serviceId, int $quantity, int $staffId, string $role): void {
    if (!in_array($role, ['doctor', 'lab', 'pharmacy'], true)) {
        throw new RuntimeException('Your department cannot add billable services.');
    }
    if ($visitId <= 0 || $serviceId <= 0 || $quantity < 1 || $quantity > 1000 || $staffId <= 0) {
        throw new InvalidArgumentException('Choose a service and a quantity between 1 and 1,000.');
    }

    $pdo->beginTransaction();
    try {
        $visitQuery = $pdo->prepare("SELECT id, patient_id FROM visits WHERE id=? AND status='open' FOR UPDATE");
        $visitQuery->execute([$visitId]);
        $visit = $visitQuery->fetch();
        if (!$visit) {
            throw new RuntimeException('This visit is closed. Refresh the patient record and try again.');
        }

        $service = $pdo->prepare('SELECT name, department, price FROM service_catalog WHERE id=? AND department=? AND active=1');
        $service->execute([$serviceId, $role]);
        $service = $service->fetch();
        if (!$service) {
            throw new RuntimeException('That service is unavailable for your department.');
        }

        $unitCents = (int)round((float)$service['price'] * 100);
        $totalCents = $unitCents * $quantity;
        if ($unitCents <= 0 || $totalCents > 999999999999) {
            throw new InvalidArgumentException('The service total is outside the supported amount range.');
        }

        $insert = $pdo->prepare('INSERT INTO visit_services (visit_id, service_id, service_name, department, quantity, unit_price, line_total, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([
            $visitId,
            $serviceId,
            $service['name'],
            $service['department'],
            $quantity,
            format_billing_cents($unitCents),
            format_billing_cents($totalCents),
            $staffId,
        ]);
        invalidate_visit_clearance($pdo, $visitId);
        audit_log($pdo, (int)$visit['patient_id'], 'visit_service_add', 'service', null, $service['name'] . ' x' . $quantity . ' (' . format_billing_cents($totalCents) . ')');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function record_custom_doctor_service(PDO $pdo, int $visitId, $name, $amount, int $staffId, string $role): void {
    if ($role !== 'doctor') {
        throw new RuntimeException('Only doctors can add custom doctor service fees.');
    }
    if ($visitId <= 0 || $staffId <= 0) {
        throw new InvalidArgumentException('Choose a valid visit for this service fee.');
    }

    $name = trim(billing_string_value($name, 'service name'));
    if ($name === '' || strlen($name) > 150) {
        throw new InvalidArgumentException('Enter a service name of 1 to 150 characters.');
    }
    $amountCents = billing_amount_cents($amount);

    $pdo->beginTransaction();
    try {
        $visitQuery = $pdo->prepare("SELECT id, patient_id FROM visits WHERE id=? AND status='open' FOR UPDATE");
        $visitQuery->execute([$visitId]);
        $visit = $visitQuery->fetch();
        if (!$visit) {
            throw new RuntimeException('This visit is closed. Refresh the patient record and try again.');
        }

        $price = format_billing_cents($amountCents);
        $insert = $pdo->prepare('INSERT INTO visit_services (visit_id, service_id, service_name, department, quantity, unit_price, line_total, recorded_by) VALUES (?, NULL, ?, ?, 1, ?, ?, ?)');
        $insert->execute([$visitId, $name, $role, $price, $price, $staffId]);
        invalidate_visit_clearance($pdo, $visitId);
        audit_log($pdo, (int)$visit['patient_id'], 'visit_service_add', 'service', null, $name . ' x1 (' . $price . ')');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function record_visit_payment(PDO $pdo, int $visitId, $amount, string $method, ?string $reference, string $collectionPoint, int $staffId, string $role): void {
    $amountCents = billing_amount_cents($amount);
    if ($visitId <= 0 || $staffId <= 0) {
        throw new InvalidArgumentException('Choose a valid visit for this payment.');
    }
    $methods = ['cash', 'mobile_money', 'card', 'bank_transfer'];
    if (!in_array($method, $methods, true)) {
        throw new InvalidArgumentException('Choose a valid payment method.');
    }
    if (!in_array($collectionPoint, ['reception', 'pharmacy'], true)
        || ($role === 'receptionist' && $collectionPoint !== 'reception')
        || ($role === 'pharmacy' && $collectionPoint !== 'pharmacy')
        || !in_array($role, ['receptionist', 'pharmacy'], true)) {
        throw new RuntimeException('Your account cannot record payments at this collection point.');
    }

    $reference = trim(billing_string_value($reference, 'payment reference'));
    if (strlen($reference) > 100) {
        throw new InvalidArgumentException('Payment reference must be 100 characters or fewer.');
    }

    $pdo->beginTransaction();
    try {
        $visit = $pdo->prepare('SELECT id, patient_id, patient_type, pharmacy_cleared FROM visits WHERE id=? AND status=? FOR UPDATE');
        $visit->execute([$visitId, 'open']);
        $visit = $visit->fetch();
        if (!$visit) {
            throw new RuntimeException('This visit is closed. Refresh the payment queue and try again.');
        }
        if ($visit['pharmacy_cleared']) {
            throw new RuntimeException('This bill has already been cleared by pharmacy.');
        }
        if ($role === 'receptionist' && $visit['patient_type'] !== 'outpatient') {
            throw new RuntimeException('Reception can only collect payments for outpatients.');
        }

        $billing = visit_billing_summary($pdo, $visitId);
        if ($billing['balance_cents'] <= 0) {
            throw new RuntimeException('There is no outstanding balance to collect.');
        }
        if ($amountCents > $billing['balance_cents']) {
            throw new InvalidArgumentException('Payment cannot exceed the outstanding balance of UGX ' . number_format($billing['balance_cents'] / 100, 2) . '.');
        }

        $insert = $pdo->prepare('INSERT INTO visit_payments (visit_id, amount, method, reference, collection_point, collected_by) VALUES (?, ?, ?, ?, ?, ?)');
        $insert->execute([
            $visitId,
            format_billing_cents($amountCents),
            $method,
            $reference !== '' ? $reference : null,
            $collectionPoint,
            $staffId,
        ]);
        audit_log($pdo, (int)$visit['patient_id'], 'visit_payment_record', 'payment', null, format_billing_cents($amountCents) . ' ' . $collectionPoint);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function clear_visit_payment(PDO $pdo, int $visitId, int $staffId, string $role): void {
    if ($visitId <= 0 || $staffId <= 0) {
        throw new InvalidArgumentException('Choose a valid visit to clear.');
    }
    if ($role !== 'pharmacy') {
        throw new RuntimeException('Only pharmacy can give final payment clearance.');
    }

    $pdo->beginTransaction();
    try {
        $visit = $pdo->prepare("SELECT id, patient_id, pharmacy_cleared FROM visits WHERE id=? AND status='open' FOR UPDATE");
        $visit->execute([$visitId]);
        $visit = $visit->fetch();
        if (!$visit) {
            throw new RuntimeException('This visit is closed. Refresh the pharmacy queue and try again.');
        }
        if ($visit['pharmacy_cleared']) {
            throw new RuntimeException('This bill has already been cleared.');
        }

        $billing = visit_billing_summary($pdo, $visitId);
        if ($billing['total_cents'] <= 0) {
            throw new RuntimeException('No billable services have been recorded for this visit yet.');
        }
        if ($billing['balance_cents'] > 0) {
            throw new RuntimeException('Collect the outstanding UGX ' . number_format($billing['balance_cents'] / 100, 2) . ' before clearing this bill.');
        }

        $update = $pdo->prepare('UPDATE visits SET pharmacy_cleared=1, pharmacy_cleared_by=?, pharmacy_cleared_at=NOW() WHERE id=? AND pharmacy_cleared=0');
        $update->execute([$staffId, $visitId]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('The bill clearance changed. Refresh the queue and try again.');
        }
        audit_log($pdo, (int)$visit['patient_id'], 'pharmacy_payment_clearance', 'payment', 'uncleared', 'cleared');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
