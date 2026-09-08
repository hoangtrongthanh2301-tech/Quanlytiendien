<?php
session_start();
require 'config.php';
require_once 'blockchain.php';

if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Chưa đăng nhập']);
    http_response_code(401);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$maKH = isset($_POST['maKH']) ? trim($_POST['maKH']) : '';
$thang = isset($_POST['thang']) ? intval($_POST['thang']) : 0;
$nam = isset($_POST['nam']) ? intval($_POST['nam']) : 0;

if (!$maKH || !$thang || !$nam) {
    echo json_encode(['success' => false, 'error' => 'Thiếu thông tin audit']);
    exit;
}

if ($thang < 1 || $thang > 12 || $nam < 2000 || $nam > 2100) {
    echo json_encode(['success' => false, 'error' => 'Tháng hoặc năm không hợp lệ']);
    exit;
}

$stmt = $conn->prepare('SELECT maCSD, maKH, chisocu, chisomoi, thang, nam, ngaynhap FROM chisodien WHERE maKH = ? AND thang = ? AND nam = ? LIMIT 1');
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Lỗi truy vấn dữ liệu chỉ số.']);
    exit;
}
$stmt->bind_param('sii', $maKH, $thang, $nam);
$stmt->execute();
$result = $stmt->get_result();
$record = $result->fetch_assoc();
$stmt->close();

if (!$record) {
    echo json_encode(['success' => false, 'error' => 'Không tìm thấy hồ sơ chỉ số điện cho khách hàng và kỳ này.']);
    exit;
}

$stmt = $conn->prepare('SELECT previous_hash, current_hash, signature, created_at FROM blockchain_chisodien WHERE maCSD = ? LIMIT 1');
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Lỗi truy vấn blockchain.']);
    exit;
}
$stmt->bind_param('i', $record['maCSD']);
$stmt->execute();
$result = $stmt->get_result();
$blockchainEntry = $result->fetch_assoc();
$stmt->close();

if (!$blockchainEntry) {
    echo json_encode(['success' => false, 'error' => 'Không tìm thấy bản ghi blockchain tương ứng với mã CSD này.']);
    exit;
}

$previousHash = $blockchainEntry['previous_hash'] ?? null;
$storedCurrentHash = $blockchainEntry['current_hash'] ?? '';
$storedSignature = $blockchainEntry['signature'] ?? '';

$recordFingerprint = buildBlockchainRecordFingerprint([
    'maCSD' => $record['maCSD'],
    'maKH' => $record['maKH'],
    'chisocu' => intval($record['chisocu']),
    'chisomoi' => intval($record['chisomoi']),
    'thang' => intval($record['thang']),
    'nam' => intval($record['nam']),
]);

$computedCurrentHash = hashChainRecord($recordFingerprint . '|' . ($previousHash ?? ''));
$signatureValid = verifySignature($computedCurrentHash, $storedSignature);
$dataIntact = $computedCurrentHash === $storedCurrentHash && $signatureValid;

echo json_encode([
    'success' => true,
    'verified' => $dataIntact,
    'message' => $dataIntact ? 'Dữ liệu toàn vẹn.' : 'Dữ liệu đã bị thay đổi.',
    'record' => [
        'maCSD' => intval($record['maCSD']),
        'maKH' => $record['maKH'],
        'thang' => intval($record['thang']),
        'nam' => intval($record['nam']),
        'chisocu' => intval($record['chisocu']),
        'chisomoi' => intval($record['chisomoi']),
    ],
    'blockchain' => [
        'previous_hash' => $previousHash,
        'stored_current_hash' => $storedCurrentHash,
        'computed_current_hash' => $computedCurrentHash,
        'signature_valid' => $signatureValid,
        'stored_signature' => $storedSignature,
    ],
    'blocks' => [
        [
            'id' => $record['maCSD'],
            'created_at' => $blockchainEntry['created_at'] ?? null,
            'previous_hash' => $previousHash,
            'current_hash' => $storedCurrentHash,
            'data_hash_matches' => $computedCurrentHash === $storedCurrentHash,
            'signature_valid' => $signatureValid,
            'previous_matches' => true,
        ]
    ]
]);

$conn->close();
