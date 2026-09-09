
<?php

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$checks = [
    'database' => false,
    'storage' => is_dir(__DIR__ . '/storage') && is_writable(__DIR__ . '/storage'),
];

$databaseConnection = $GLOBALS['conn'] ?? null;
$result = $databaseConnection instanceof mysqli
    ? mysqli_query($databaseConnection, 'SELECT 1')
    : false;

if ($result) {
    $checks['database'] = true;
}

if (!$checks['database'] || !$checks['storage']) {
    http_response_code(503);
    echo json_encode(['status' => 'unhealthy', 'checks' => $checks]);
    exit;
}

echo json_encode(['status' => 'ok', 'checks' => $checks]);
