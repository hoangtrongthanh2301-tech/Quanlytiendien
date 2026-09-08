<?php
session_start();
require 'config.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    exit;
}

$maHD = isset($_GET['maHD']) ? intval($_GET['maHD']) : 0;
$maTT = isset($_GET['maTT']) ? intval($_GET['maTT']) : 0;

if ($maHD <= 0 || $maTT <= 0) {
    http_response_code(400);
    exit;
}

// Lấy thông tin thanh toán
$stmt = $conn->prepare(
    "SELECT h.maHD, h.maKH, tt.maTT, tt.sotien, t.hovaten " .
    "FROM hoadon h " .
    "JOIN thanhtoan tt ON h.maHD = tt.maHD " .
    "JOIN taikhoan t ON h.maKH = t.maKH " .
    "WHERE h.maHD = ? AND tt.maTT = ? AND tt.trangthai = 'chuathanhtoan'"
);
$stmt->bind_param('ii', $maHD, $maTT);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    exit;
}

// Tạo payload cho QR code
$qrData = json_encode([
    'maHD' => $row['maHD'],
    'maTT' => $row['maTT'],
    'maKH' => $row['maKH'],
    'hovaten' => $row['hovaten'],
    'sotien' => $row['sotien'],
    'timestamp' => time(),
    'url' => 'payment-confirm.php'
]);

// Sử dụng Google Charts API để tạo QR code
$qrUrl = 'https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl=' . urlencode($qrData) . '&choe=UTF-8';

// Lấy QR code image từ Google
$qrImage = @file_get_contents($qrUrl);

if ($qrImage === false) {
    // Nếu không thể lấy từ Google, trả về placeholder
    header('Content-Type: text/html; charset=utf-8');
    echo '<div style="display:flex;align-items:center;justify-content:center;width:300px;height:300px;background:#f0f0f0;border-radius:10px;font-size:18px;color:#999;">QR Code không khả dụng</div>';
    exit;
}

// Trả về QR code image
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
echo $qrImage;

$conn->close();
?>
