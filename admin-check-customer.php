<?php
require_once __DIR__ . '/require-admin.php';
require 'config.php';
header('Content-Type: application/json; charset=utf-8');

$maKH = isset($_GET['maKH']) ? strtoupper(trim($_GET['maKH'])) : '';
if ($maKH === '') {
    echo json_encode(['success' => false, 'error' => 'Thiếu mã khách hàng.']);
    exit;
}

$stmt = $conn->prepare(
    "SELECT t.maKH, t.hovaten, t.email, t.sodienthoai, t.diachi, " .
    "COALESCE((SELECT SUM(h.tongtien) FROM hoadon h WHERE h.maKH = t.maKH AND h.trangthai = 'chuathanhtoan'), 0) AS conThieu, " .
    "(SELECT COUNT(*) FROM hoadon h WHERE h.maKH = t.maKH AND h.trangthai = 'chuathanhtoan') AS soHoaDonChuaThanhToan " .
    "FROM taikhoan t WHERE t.maKH = ? LIMIT 1"
);
$stmt->bind_param('s', $maKH);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) {
    echo json_encode(['success' => false, 'error' => 'Khách hàng không tồn tại.']);
    $conn->close();
    exit;
}

$conThieu = round(floatval($customer['conThieu']), 2);
$canDelete = $conThieu <= 0.009;

echo json_encode([
    'success' => true,
    'customer' => [
        'maKH' => $customer['maKH'],
        'hovaten' => $customer['hovaten'],
        'email' => $customer['email'],
        'sodienthoai' => $customer['sodienthoai'],
        'diachi' => $customer['diachi']
    ],
    'conThieu' => $conThieu,
    'soHoaDonChuaThanhToan' => intval($customer['soHoaDonChuaThanhToan']),
    'canDelete' => $canDelete
]);
$conn->close();
