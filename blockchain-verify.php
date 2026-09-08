<?php
require 'config.php';
require 'blockchain.php';

header('Content-Type: application/json; charset=utf-8');

$result = verifyBlockchainChain($conn);
if (!$result['ok']) {
    echo json_encode(['success' => false, 'error' => $result['error']]);
    exit;
}

$problems = [];
foreach ($result['rows'] as $row) {
    if (!$row['valid_hash'] || !$row['valid_previous'] || !$row['valid_signature']) {
        $problems[] = [
            'id' => intval($row['id']),
            'maCSD' => intval($row['maCSD']),
            'maKH' => $row['maKH'],
            'valid_hash' => $row['valid_hash'],
            'valid_previous' => $row['valid_previous'],
            'valid_signature' => $row['valid_signature']
        ];
    }
}

echo json_encode([
    'success' => true,
    'verified' => count($problems) === 0,
    'problems' => $problems,
    'total' => count($result['rows'])
]);

$conn->close();
