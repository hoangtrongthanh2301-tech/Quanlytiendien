<?php
session_start();
require 'config.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Database chưa sẵn sàng']);
    exit;
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Chưa đăng nhập']);
    http_response_code(401);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Chỉ chấp nhận POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Phương thức không được phép']);
    exit;
}

// Lấy dữ liệu từ POST
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Dữ liệu không hợp lệ']);
    exit;
}

$maTT = isset($data['maTT']) ? intval($data['maTT']) : 0;
$maHD = isset($data['maHD']) ? intval($data['maHD']) : 0;
$phuongthuc = isset($data['phuongthuc']) ? trim($data['phuongthuc']) : 'QR Code';
$sessionCustomerCode = strtoupper(trim((string) ($_SESSION['user']['maKH'] ?? '')));

if ($maTT <= 0 || $maHD <= 0 || $sessionCustomerCode === '') {
    echo json_encode(['success' => false, 'error' => 'Mã thanh toán hoặc mã hóa đơn không hợp lệ']);
    exit;
}

if (!$conn->begin_transaction()) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Không thể bắt đầu giao dịch']);
    exit;
}

// Lock the payment and verify ownership before changing its state.
$stmt = $conn->prepare(
    "SELECT tt.maTT, tt.maHD, tt.sotien
     FROM thanhtoan tt
     INNER JOIN hoadon h ON h.maHD = tt.maHD AND h.maKH = tt.maKH
     WHERE tt.maTT = ? AND tt.maHD = ? AND tt.maKH = ?
       AND tt.trangthai = 'chuathanhtoan'
     LIMIT 1 FOR UPDATE"
);

if (!$stmt) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Không thể chuẩn bị kiểm tra thanh toán']);
    exit;
}

$stmt->bind_param('iis', $maTT, $maHD, $sessionCustomerCode);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();
$stmt->close();

if (!$payment) {
    $conn->rollback();
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Thanh toán không tồn tại hoặc đã thanh toán']);
    exit;
}

// Cập nhật trạng thái thanh toán
$stmt = $conn->prepare(
    "UPDATE thanhtoan SET trangthai = 'dathanhtoan', phuongthuc = ?, ngaythanhtoan = CURRENT_TIMESTAMP WHERE maTT = ?"
);

if (!$stmt) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Lỗi chuẩn bị câu lệnh: ' . $conn->error]);
    exit;
}

$stmt->bind_param('si', $phuongthuc, $maTT);
$executeResult = $stmt->execute();

if (!$executeResult) {
    echo json_encode(['success' => false, 'error' => 'Lỗi cập nhật: ' . $stmt->error]);
    $stmt->close();
    $conn->rollback();
    exit;
}

$stmt->close();

// Kiểm tra xem tất cả thanh toán của hóa đơn đã xong chưa, nếu có thì cập nhật hóa đơn
$stmt = $conn->prepare(
    "UPDATE hoadon SET trangthai = 'dathanhtoan' WHERE maHD = ? AND NOT EXISTS (
        SELECT 1 FROM thanhtoan WHERE maHD = ? AND trangthai = 'chuathanhtoan'
    )"
);

if ($stmt) {
    $stmt->bind_param('ii', $maHD, $maHD);
    if (!$stmt->execute()) {
        $stmt->close();
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Không thể cập nhật trạng thái hóa đơn']);
        exit;
    }
    $stmt->close();
} else {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Không thể cập nhật trạng thái hóa đơn']);
    exit;
}

if (!$conn->commit()) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Không thể hoàn tất giao dịch thanh toán']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Thanh toán thành công',
    'maTT' => $maTT,
    'maHD' => $maHD,
    'sotien' => floatval($payment['sotien']),
    'phuongthuc' => $phuongthuc,
    'ngaythanhtoan' => date('Y-m-d H:i:s')
]);

$conn->close();
?>
