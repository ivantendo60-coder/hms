<?php
header('Content-Type: application/json');

$action = strtolower(trim($_REQUEST['action'] ?? 'check'));
$port = trim($_REQUEST['port'] ?? '');
$baseDir = dirname(__DIR__);
$scriptPath = $baseDir . DIRECTORY_SEPARATOR . 'arduino' . DIRECTORY_SEPARATOR . 'serial_bridge.py';
$logPath = $baseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'rfid_bridge.log';
$probePath = $baseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'rfid_bridge_probe.json';

function quoteForShell($value) {
    $value = (string) $value;
    if (stripos(PHP_OS, 'WIN') !== false) {
        return '"' . str_replace('"', '\\"', $value) . '"';
    }
    return escapeshellarg($value);
}

function buildPythonCommand($pythonCommand, $scriptPath, array $args = []) {
    $parts = preg_split('/\s+/', trim((string) $pythonCommand));
    $parts = array_values(array_filter($parts, static function ($part) {
        return $part !== '';
    }));
    if (!$parts) {
        return '';
    }

    $command = [];
    foreach ($parts as $part) {
        $command[] = quoteForShell($part);
    }
    $command[] = quoteForShell($scriptPath);
    foreach ($args as $arg) {
        $command[] = quoteForShell($arg);
    }

    return implode(' ', $command);
}

function findPythonCommand() {
    $candidates = ['python', 'python3', 'py'];
    foreach ($candidates as $candidate) {
        $output = [];
        $code = 0;
        @exec($candidate . ' --version 2>&1', $output, $code);
        if ($code === 0 && !empty($output)) {
            return $candidate;
        }
    }

    $pythonExe = getenv('PYTHON_EXE');
    if ($pythonExe && is_executable($pythonExe)) {
        return $pythonExe;
    }

    return null;
}

if (!file_exists($scriptPath)) {
    echo json_encode(['ok' => false, 'message' => 'Bridge script not found.']);
    exit;
}

$python = findPythonCommand();
if (!$python) {
    echo json_encode(['ok' => false, 'message' => 'Python is not available on the server. Install Python and pyserial first.']);
    exit;
}

if ($action === 'start') {
    $args = [];
    if ($port !== '') {
        $args[] = '--port';
        $args[] = $port;
    }

    $command = buildPythonCommand($python, $scriptPath, $args);
    if (stripos(PHP_OS, 'WIN') !== false) {
        $command = 'start /B "" ' . $command . ' > ' . quoteForShell($logPath) . ' 2>&1';
    } else {
        $command = 'nohup ' . $command . ' > ' . quoteForShell($logPath) . ' 2>&1 &';
    }

    @exec($command, $output, $code);
    $started = $code === 0 || file_exists($logPath);
    echo json_encode([
        'ok' => $started,
        'action' => 'start',
        'message' => $started ? 'Bridge start command submitted.' : 'The bridge could not be started.',
        'port' => $port ?: null,
        'log' => str_replace($baseDir . DIRECTORY_SEPARATOR, '', $logPath),
        'python' => $python,
        'command' => $command,
    ]);
    exit;
}

$checkArgs = ['--check'];
$command = buildPythonCommand($python, $scriptPath, $checkArgs);
if (stripos(PHP_OS, 'WIN') !== false) {
    $command = $command . ' > ' . quoteForShell($probePath) . ' 2>&1';
} else {
    $command = $command . ' > ' . quoteForShell($probePath) . ' 2>&1';
}

$output = [];
$code = 0;
@exec($command, $output, $code);
$stdout = '';
if (file_exists($probePath)) {
    $stdout = trim((string) file_get_contents($probePath));
}

$payload = null;
if ($stdout !== '') {
    $decoded = json_decode($stdout, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

if (!is_array($payload)) {
    $payload = ['ok' => false, 'message' => $stdout ?: 'The bridge probe did not return any output.'];
}

$payload['ok'] = !empty($payload['ok']) || $code === 0;
$payload['action'] = 'check';
$payload['python'] = $python;
$payload['script'] = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $scriptPath);
$payload['probe_file'] = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $probePath);

echo json_encode($payload);
