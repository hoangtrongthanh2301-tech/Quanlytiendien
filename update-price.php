<?php
header('Content-Type: application/json; charset=utf-8');
require 'config.php';

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) {
    echo json_encode(['success' => false, 'error' => 'Dữ liệu không hợp lệ.']);
    exit;
}

$effectiveDate = isset($payload['effective_date']) ? trim($payload['effective_date']) : '';
$prices = isset($payload['prices']) && is_array($payload['prices']) ? $payload['prices'] : [];

if (!$effectiveDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $effectiveDate) || !strtotime($effectiveDate)) {
    echo json_encode(['success' => false, 'error' => 'Ngày áp dụng không hợp lệ.']);
    exit;
}

if (count($prices) !== 5) {
    echo json_encode(['success' => false, 'error' => 'Cần cung cấp giá cho 5 bậc.']);
    exit;
}

foreach ($prices as $price) {
    if (!isset($price['bac'], $price['sanluong'], $price['dongia']) || !is_numeric($price['bac']) || !is_numeric($price['sanluong']) || !is_numeric($price['dongia']) || $price['dongia'] <= 0) {
        echo json_encode(['success' => false, 'error' => 'Giá phải là số hợp lệ cho mỗi bậc.']);
        exit;
    }
}

$maxRes = mysqli_query($conn, 'SELECT IFNULL(MAX(maGD), 0) AS maxId FROM giadien');
if (!$maxRes) {
    echo json_encode(['success' => false, 'error' => 'Lỗi truy vấn mã giá: ' . mysqli_error($conn)]);
    exit;
}
$maxRow = mysqli_fetch_assoc($maxRes);
$currentId = intval($maxRow['maxId']);

$insertStmt = mysqli_prepare($conn, 'INSERT INTO giadien (maGD, bac, sanluong, dongia, ngayapdung) VALUES (?, ?, ?, ?, ?)');
if (!$insertStmt) {
    echo json_encode(['success' => false, 'error' => 'Không thể chuẩn bị truy vấn cập nhật giá.']);
    exit;
}

foreach ($prices as $price) {
    $currentId++;
    $bac = intval($price['bac']);
    $sanluong = intval($price['sanluong']);
    $dongia = floatval($price['dongia']);
    mysqli_stmt_bind_param($insertStmt, 'iiids', $currentId, $bac, $sanluong, $dongia, $effectiveDate);
    if (!mysqli_stmt_execute($insertStmt)) {
        echo json_encode(['success' => false, 'error' => 'Lỗi lưu giá bậc ' . $bac . ': ' . mysqli_stmt_error($insertStmt)]);
        mysqli_stmt_close($insertStmt);
        exit;
    }
}

mysqli_stmt_close($insertStmt);

echo json_encode(['success' => true]);
