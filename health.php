
<?php

error_reporting(0);
mysqli_report(MYSQLI_REPORT_OFF);

header('Content-Type: application/json; charset=utf-8');

$checks = [
    'database' => false,
    'storage' => is_dir(__DIR__ . '/storage') && is_writable(__DIR__ . '/storage'),
];

$host = getenv('DB_HOST') ?: '127.0.0.1';
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$database = getenv('DB_NAME') ?: 'qltiendien';
$port = (int) (getenv('DB_PORT') ?: 3306);
$connectTimeout = (int) (getenv('DB_CONNECT_TIMEOUT') ?: 3);
$readTimeout = (int) (getenv('DB_READ_TIMEOUT') ?: 8);

$conn = @mysqli_init();
if ($conn) {
    @mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, $connectTimeout);
    @mysqli_options($conn, MYSQLI_OPT_READ_TIMEOUT, $readTimeout);

    $connected = @mysqli_real_connect($conn, $host, $user, $password, $database, $port);
    if ($connected) {
        @mysqli_set_charset($conn, 'utf8mb4');
        $result = @mysqli_query($conn, 'SELECT 1');
        if ($result) {
            $checks['database'] = true;
            @mysqli_free_result($result);
        }
        @mysqli_close($conn);
    }
}

if (!$checks['database'] || !$checks['storage']) {
    http_response_code(503);
    echo json_encode(['status' => 'unhealthy', 'checks' => $checks]);
    exit;
}

echo json_encode(['status' => 'ok', 'checks' => $checks]);
