<?php

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$result = mysqli_query($conn, 'SELECT 1');

if (!$result) {
    http_response_code(503);
    echo json_encode(['status' => 'unhealthy']);
    exit;
}

echo json_encode(['status' => 'ok']);
