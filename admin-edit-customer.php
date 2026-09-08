<?php
require_once __DIR__ . '/require-admin.php';
require 'config.php';
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Dữ liệu không hợp lệ.']);
    exit;
}

$maKH = isset($data['maKH']) ? strtoupper(trim($data['maKH'])) : '';
if (!$maKH) {
    echo json_encode(['success' => false, 'error' => 'Thiếu mã khách hàng.']);
    exit;
}

$stmt = $conn->prepare("SELECT maKH, hovaten, email, sodienthoai, matkhau, quyen, trangthai, diachi FROM taikhoan WHERE maKH = ? LIMIT 1");
$stmt->bind_param('s', $maKH);
$stmt->execute();
$result = $stmt->get_result();
$current = $result->fetch_assoc();
$stmt->close();

if (!$current) {
    echo json_encode(['success' => false, 'error' => 'Khách hàng không tồn tại.']);
    $conn->close();
    exit;
}

$hovaten = isset($data['hovaten']) && $data['hovaten'] !== '' ? trim($data['hovaten']) : $current['hovaten'];
$email = isset($data['email']) && $data['email'] !== '' ? trim($data['email']) : $current['email'];
$sodienthoai = isset($data['sodienthoai']) && $data['sodienthoai'] !== '' ? trim($data['sodienthoai']) : $current['sodienthoai'];
$matkhau = isset($data['matkhau']) && $data['matkhau'] !== '' ? trim($data['matkhau']) : $current['matkhau'];
$quyen = isset($data['quyen']) ? trim($data['quyen']) : $current['quyen'];
$trangthai = isset($data['trangthai']) ? trim($data['trangthai']) : $current['trangthai'];
$diachi = isset($data['diachi']) ? trim($data['diachi']) : $current['diachi'];

if ($email !== $current['email'] || $sodienthoai !== $current['sodienthoai']) {
    $stmt = $conn->prepare("SELECT maKH FROM taikhoan WHERE (email = ? OR sodienthoai = ?) AND maKH != ? LIMIT 1");
    $stmt->bind_param('sss', $email, $sodienthoai, $maKH);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Email hoặc số điện thoại đã được sử dụng bởi khách hàng khác.']);
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();
}

$stmt = $conn->prepare("UPDATE taikhoan SET hovaten = ?, email = ?, sodienthoai = ?, matkhau = ?, quyen = ?, trangthai = ?, diachi = ? WHERE maKH = ?");
$stmt->bind_param('ssssssss', $hovaten, $email, $sodienthoai, $matkhau, $quyen, $trangthai, $diachi, $maKH);
$success = $stmt->execute();
$error = $stmt->error;
$stmt->close();

if (!$success) {
    echo json_encode(['success' => false, 'error' => 'Không thể cập nhật khách hàng: ' . $error]);
    $conn->close();
    exit;
}

echo json_encode(['success' => true, 'message' => 'Cập nhật khách hàng thành công.']);
$conn->close();
