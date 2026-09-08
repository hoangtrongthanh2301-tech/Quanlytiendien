<?php
require_once __DIR__ . '/require-admin.php';
require 'config.php';
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Dữ liệu không hợp lệ.']);
    exit;
}

$maTinh = preg_replace('/\D/', '', (string) ($data['maTinh'] ?? ''));
$maXa = preg_replace('/\D/', '', (string) ($data['maXa'] ?? ''));
$hovaten = isset($data['hovaten']) ? trim($data['hovaten']) : '';
$email = isset($data['email']) ? trim($data['email']) : '';
$sodienthoai = isset($data['sodienthoai']) ? trim($data['sodienthoai']) : '';
$matkhau = isset($data['matkhau']) ? trim($data['matkhau']) : '';
$quyen = isset($data['quyen']) ? trim($data['quyen']) : 'khachhang';
$trangthai = isset($data['trangthai']) ? trim($data['trangthai']) : 'hoatdong';
$diachi = isset($data['diachi']) ? trim($data['diachi']) : '';

if (!$maTinh || !$maXa || !$hovaten || !$email || !$sodienthoai || !$matkhau) {
    echo json_encode(['success' => false, 'error' => 'Thiếu tỉnh/xã hoặc dữ liệu bắt buộc.']);
    exit;
}

$maTinh = str_pad($maTinh, 2, '0', STR_PAD_LEFT);
$maXa = str_pad($maXa, 5, '0', STR_PAD_LEFT);

$conn->begin_transaction();
$addressStmt = $conn->prepare(
    'SELECT ma_dien_luc FROM don_vi_hanh_chinh WHERE ma_tinh = ? AND ma_xa = ? LIMIT 1 FOR UPDATE'
);
if (!$addressStmt) {
    echo json_encode(['success' => false, 'error' => 'Chưa có dữ liệu đơn vị hành chính mới trong database.']);
    $conn->close();
    exit;
}
$addressStmt->bind_param('ss', $maTinh, $maXa);
$addressStmt->execute();
$addressRow = $addressStmt->get_result()->fetch_assoc();
$addressStmt->close();
if (!$addressRow) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Tỉnh/thành phố hoặc phường/xã không tồn tại trong dữ liệu hành chính.']);
    $conn->close();
    exit;
}

$maDienLuc = strtoupper(trim($addressRow['ma_dien_luc']));
$codePrefix = $maDienLuc . $maTinh . $maXa;
$usedStmt = $conn->prepare("SELECT maKH FROM taikhoan WHERE maKH LIKE CONCAT(?, '%') FOR UPDATE");
$usedStmt->bind_param('s', $codePrefix);
$usedStmt->execute();
$usedResult = $usedStmt->get_result();
$usedNumbers = [];
while ($usedRow = $usedResult->fetch_assoc()) {
    $suffix = substr($usedRow['maKH'], strlen($codePrefix));
    if (preg_match('/^\d{6}$/', $suffix)) {
        $usedNumbers[(int) $suffix] = true;
    }
}
$usedStmt->close();

$sequence = 1;
while (isset($usedNumbers[$sequence]) && $sequence <= 999999) {
    $sequence++;
}
if ($sequence > 999999) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Khu vực này đã hết số thứ tự khách hàng.']);
    $conn->close();
    exit;
}
$maKH = $codePrefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);

$stmt = $conn->prepare("SELECT maKH FROM taikhoan WHERE maKH = ? OR email = ? OR sodienthoai = ? LIMIT 1");
$stmt->bind_param('sss', $maKH, $email, $sodienthoai);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Mã khách hàng, email hoặc số điện thoại đã tồn tại.']);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

$stmt = $conn->prepare("INSERT INTO taikhoan (maKH, hovaten, email, sodienthoai, matkhau, quyen, trangthai, diachi) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param('ssssssss', $maKH, $hovaten, $email, $sodienthoai, $matkhau, $quyen, $trangthai, $diachi);
$success = $stmt->execute();
$error = $stmt->error;
$stmt->close();

if (!$success) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Không thể thêm khách hàng: ' . $error]);
    $conn->close();
    exit;
}

$conn->commit();
echo json_encode(['success' => true, 'message' => 'Thêm khách hàng thành công.', 'maKH' => $maKH]);
$conn->close();
