<?php
session_start();
require 'config.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Chưa đăng nhập']);
    http_response_code(401);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$maKH = isset($_GET['maKH']) ? strtoupper(trim($_GET['maKH'])) : '';
if ($maKH === '') {
    echo json_encode(['success' => false, 'error' => 'Vui lòng nhập mã khách hàng']);
    exit;
}

// Kiểm tra khách hàng có tồn tại hay không
$stmt = $conn->prepare("SELECT maKH, hovaten FROM taikhoan WHERE maKH = ?");
$stmt->bind_param('s', $maKH);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();
$stmt->close();

if (!$customer) {
    echo json_encode(['success' => false, 'error' => 'Không tìm thấy khách hàng']);
    exit;
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

$countStmt = $conn->prepare(
    "SELECT COUNT(*) AS total " .
    "FROM thanhtoan tt " .
    "JOIN hoadon h ON tt.maHD = h.maHD " .
    "WHERE tt.maKH = ?"
);
$countStmt->bind_param('s', $maKH);
$countStmt->execute();
$countResult = $countStmt->get_result()->fetch_assoc();
$countStmt->close();

$totalTransactions = intval($countResult['total'] ?? 0);
$totalPages = $totalTransactions > 0 ? intval(ceil($totalTransactions / $perPage)) : 1;
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

function normalizePaymentMethod($method, $status) {
    $method = trim((string)($method ?? ''));
    if ($status !== 'dathanhtoan') {
        return $method ?: 'Chưa thanh toán';
    }
    $lower = mb_strtolower($method, 'UTF-8');
    if ($lower === '' || $lower === 'chua thanh toan') {
        return 'Tiền mặt';
    }
    if (str_contains($lower, 'qr')) {
        return 'QR Code';
    }
    if (str_contains($lower, 'tiền') || str_contains($lower, 'tien') || str_contains($lower, 'cash')) {
        return 'Tiền mặt';
    }
    if (str_contains($lower, 'bank') || str_contains($lower, 'atm') || str_contains($lower, 'transfer')) {
        return 'QR Code';
    }
    return 'Tiền mặt';
}

function normalizePaymentDate($date, $invoiceMonth, $invoiceYear, $status) {
    if ($status !== 'dathanhtoan') {
        return $date;
    }
    $fallback = sprintf('%04d-%02d-15 12:00:00', intval($invoiceYear), intval($invoiceMonth));
    if (!$date) {
        return $fallback;
    }
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $date);
    if (!$dt) {
        return $fallback;
    }
    if (intval($dt->format('n')) !== intval($invoiceMonth) || intval($dt->format('Y')) !== intval($invoiceYear)) {
        return $fallback;
    }
    return $date;
}

// Lấy lịch sử thanh toán của khách hàng
$stmt = $conn->prepare(
    "SELECT tt.maTT, tt.maHD, tt.sotien, tt.phuongthuc, tt.trangthai, tt.ngaythanhtoan, c.thang AS invoice_thang, c.nam AS invoice_nam " .
    "FROM thanhtoan tt " .
    "JOIN hoadon h ON tt.maHD = h.maHD " .
    "JOIN chisodien c ON h.maCSD = c.maCSD " .
    "WHERE tt.maKH = ? " .
    "ORDER BY tt.ngaythanhtoan DESC " .
    "LIMIT ? OFFSET ?"
);
$stmt->bind_param('sii', $maKH, $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
$totalAmount = 0;
while ($row = $result->fetch_assoc()) {
    $normalizedMethod = normalizePaymentMethod($row['phuongthuc'], $row['trangthai']);
    $normalizedDate = normalizePaymentDate($row['ngaythanhtoan'], $row['invoice_thang'], $row['invoice_nam'], $row['trangthai']);

    $transactions[] = [
        'maTT' => intval($row['maTT']),
        'maHD' => intval($row['maHD']),
        'sotien' => floatval($row['sotien']),
        'phuongthuc' => $row['phuongthuc'] ?? 'Chưa thanh toán',
        'display_phuongthuc' => $normalizedMethod,
        'trangthai' => $row['trangthai'],
        'ngaythanhtoan' => $row['ngaythanhtoan'],
        'display_ngaythanhtoan' => $normalizedDate,
    ];
    if ($row['trangthai'] === 'dathanhtoan') {
        $totalAmount += floatval($row['sotien']);
    }
}
$stmt->close();

echo json_encode([
    'success' => true,
    'customer' => [
        'maKH' => $customer['maKH'],
        'hovaten' => $customer['hovaten']
    ],
    'transactions' => $transactions,
    'totalAmount' => $totalAmount
    , 'page' => $page
    , 'perPage' => $perPage
    , 'totalPages' => $totalPages
    , 'totalTransactions' => $totalTransactions
]);

$conn->close();
?>
