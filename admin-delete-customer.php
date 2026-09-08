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

$stmt = $conn->prepare("SELECT maKH FROM taikhoan WHERE maKH = ? LIMIT 1");
$stmt->bind_param('s', $maKH);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Khách hàng không tồn tại.']);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

$debtStmt = $conn->prepare(
    "SELECT COALESCE(SUM(tongtien), 0) AS conThieu " .
    "FROM hoadon WHERE maKH = ? AND trangthai = 'chuathanhtoan'"
);
$debtStmt->bind_param('s', $maKH);
$debtStmt->execute();
$debt = $debtStmt->get_result()->fetch_assoc();
$debtStmt->close();

if (floatval($debt['conThieu'] ?? 0) > 0.009) {
    echo json_encode([
        'success' => false,
        'error' => 'Không thể xóa khách hàng khi còn công nợ ' . number_format(floatval($debt['conThieu']), 0, ',', '.') . ' VNĐ. Vui lòng thanh toán trước.'
    ]);
    $conn->close();
    exit;
}

$conn->begin_transaction();

$deletes = [
    "DELETE blockchain_chisodien FROM blockchain_chisodien INNER JOIN chisodien ON chisodien.maCSD = blockchain_chisodien.maCSD WHERE chisodien.maKH = ?",
    "DELETE FROM thongketiendien WHERE maKH = ?",
    "DELETE FROM thanhtoan WHERE maKH = ?",
    "DELETE FROM hoadon WHERE maKH = ?",
    "DELETE FROM chisodien WHERE maKH = ?",
    "DELETE FROM taikhoan WHERE maKH = ?"
];

try {
    foreach ($deletes as $sql) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $maKH);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Xóa khách hàng thành công.']);
} catch (Throwable $error) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Không thể xóa khách hàng. Vui lòng kiểm tra dữ liệu liên quan rồi thử lại.']);
}
$conn->close();
