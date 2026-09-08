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

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    echo json_encode(['success' => false, 'error' => 'Vui lòng nhập mã hóa đơn hoặc mã khách hàng']);
    exit;
}

$isCustomerSearch = preg_match('/^KH/i', $q) === 1;
$invoice = null;
$pendingCount = 0;
$pendingTotal = 0.0;

if ($isCustomerSearch) {
    $maKH = strtoupper($q);
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS pendingCount, COALESCE(SUM(tt.sotien), 0) AS pendingTotal " .
        "FROM thanhtoan tt " .
        "WHERE tt.maKH = ? AND tt.trangthai = 'chuathanhtoan'"
    );
    $stmt->bind_param('s', $maKH);
    $stmt->execute();
    $result = $stmt->get_result();
    $pendingInfo = $result->fetch_assoc();
    $pendingCount = intval($pendingInfo['pendingCount']);
    $pendingTotal = floatval($pendingInfo['pendingTotal']);
    $stmt->close();

    if ($pendingCount === 0) {
        echo json_encode(['success' => false, 'error' => 'Không có hóa đơn chưa thanh toán cho khách hàng này.']);
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT h.maHD, h.maKH, h.maCSD, h.sodiendatieuthu, h.tongtien, h.trangthai, h.hansudung, " .
        "c.chisocu, c.chisomoi, c.thang, c.nam, t.hovaten, tt.maTT, tt.sotien, tt.phuongthuc, tt.trangthai AS paymentStatus " .
        "FROM hoadon h " .
        "JOIN chisodien c ON h.maCSD = c.maCSD " .
        "JOIN taikhoan t ON h.maKH = t.maKH " .
        "JOIN thanhtoan tt ON h.maHD = tt.maHD " .
        "WHERE tt.maKH = ? AND tt.trangthai = 'chuathanhtoan' " .
        "ORDER BY tt.ngaytao ASC LIMIT 1"
    );
    $stmt->bind_param('s', $maKH);
} else {
    $maHD = intval($q);
    if ($maHD <= 0) {
        echo json_encode(['success' => false, 'error' => 'Mã hóa đơn không hợp lệ']);
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT h.maHD, h.maKH, h.maCSD, h.sodiendatieuthu, h.tongtien, h.trangthai, h.hansudung, " .
        "c.chisocu, c.chisomoi, c.thang, c.nam, t.hovaten, tt.maTT, tt.sotien, tt.phuongthuc, tt.trangthai AS paymentStatus " .
        "FROM hoadon h " .
        "JOIN chisodien c ON h.maCSD = c.maCSD " .
        "JOIN taikhoan t ON h.maKH = t.maKH " .
        "JOIN thanhtoan tt ON h.maHD = tt.maHD " .
        "WHERE h.maHD = ? " .
        "ORDER BY tt.ngaythanhtoan DESC LIMIT 1"
    );
    $stmt->bind_param('i', $maHD);
}

$stmt->execute();
$result = $stmt->get_result();
$invoice = $result->fetch_assoc();
$stmt->close();

if (!$invoice) {
    echo json_encode(['success' => false, 'error' => 'Không tìm thấy hóa đơn.']);
    exit;
}

$soKWh = intval($invoice['chisomoi']) - intval($invoice['chisocu']);

// Lấy số tiền từ bảng thanhtoan
$paymentAmount = floatval($invoice['sotien']);

$stmt = $conn->prepare(
    "SELECT bacthang AS bac, dongia, sanluong AS kwh, thanhtien AS amount " .
    "FROM thongketiendien " .
    "WHERE maCSD = ? AND maKH = ? " .
    "ORDER BY bacthang ASC"
);
    $stmt->bind_param('is', intval($invoice['maCSD']), $invoice['maKH']);
$stmt->execute();
$result = $stmt->get_result();

$breakdown = [];
$subtotal = 0;
while ($row = $result->fetch_assoc()) {
    $breakdown[] = [
        'bac' => intval($row['bac']),
        'gia' => floatval($row['dongia']),
        'kwh' => intval($row['kwh']),
        'amount' => floatval($row['amount'])
    ];
    $subtotal += floatval($row['amount']);
}
$stmt->close();

if (empty($breakdown)) {
    echo json_encode(['success' => false, 'error' => 'Không tìm thấy chi tiết thống kê tiền điện.']);
    exit;
}

$tax = round($subtotal * 0.08);
$total = $paymentAmount;

$response = [
    'success' => true,
    'invoice' => [
        'maHD' => intval($invoice['maHD']),
        'maKH' => $invoice['maKH'],
        'hovaten' => $invoice['hovaten'],
        'sodiendatieuthu' => intval($invoice['sodiendatieuthu']),
        'tongtien' => floatval($invoice['tongtien']),
        'trangthai' => $invoice['trangthai'],
        'phuongthuc' => $invoice['phuongthuc'] ?? null,
        'paymentStatus' => $invoice['paymentStatus'] ?? null,
        'paymentAmount' => $paymentAmount,
        'hansudung' => $invoice['hansudung'],
        'soKWh' => $soKWh,
        'thang' => intval($invoice['thang']),
        'nam' => intval($invoice['nam'])
    ],
    'breakdown' => $breakdown,
    'subtotal' => $subtotal,
    'tax' => $tax,
    'total' => $total
];

if ($isCustomerSearch) {
    $response['pendingCount'] = $pendingCount;
    $response['pendingTotal'] = $pendingTotal;
}

echo json_encode($response);

$conn->close();
?>