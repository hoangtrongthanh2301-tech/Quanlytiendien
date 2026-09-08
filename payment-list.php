<?php
require 'config.php';
header('Content-Type: application/json; charset=utf-8');

$maKH = isset($_GET['maKH']) ? strtoupper(trim($_GET['maKH'])) : (isset($_GET['q']) ? strtoupper(trim($_GET['q'])) : '');

if ($maKH === '') {
    echo json_encode(['success' => false, 'error' => 'Vui lòng nhập mã khách hàng']);
    exit;
}

// Kiểm tra khách hàng có tồn tại hay không
$stmt = $conn->prepare("SELECT maKH, hovaten FROM taikhoan WHERE maKH = ? AND trangthai = 'hoatdong'");
$stmt->bind_param('s', $maKH);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();
$stmt->close();

if (!$customer) {
    echo json_encode(['success' => false, 'error' => 'Không tìm thấy khách hàng hoặc khách hàng đã bị khóa']);
    exit;
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$countStmt = $conn->prepare(
    "SELECT COUNT(*) AS total, COALESCE(SUM(tt.sotien), 0) AS totalAmount " .
    "FROM hoadon h " .
    "JOIN thanhtoan tt ON h.maHD = tt.maHD " .
    "WHERE h.maKH = ? AND tt.trangthai = 'chuathanhtoan'"
);
$countStmt->bind_param('s', $maKH);
$countStmt->execute();
$countResult = $countStmt->get_result()->fetch_assoc();
$countStmt->close();

$totalInvoices = intval($countResult['total'] ?? 0);
$totalAmount = floatval($countResult['totalAmount'] ?? 0);
$totalPages = $totalInvoices > 0 ? intval(ceil($totalInvoices / $perPage)) : 1;
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Lấy danh sách hóa đơn chưa thanh toán
$stmt = $conn->prepare(
    "SELECT h.maHD, h.maCSD, h.sodiendatieuthu, h.tongtien, h.trangthai, h.hansudung, h.ngaytao, " .
    "c.chisocu, c.chisomoi, c.thang, c.nam, " .
    "tt.maTT, tt.sotien, tt.trangthai AS paymentStatus " .
    "FROM hoadon h " .
    "JOIN chisodien c ON h.maCSD = c.maCSD " .
    "JOIN thanhtoan tt ON h.maHD = tt.maHD " .
    "WHERE h.maKH = ? AND tt.trangthai = 'chuathanhtoan' " .
    "ORDER BY h.ngaytao DESC, h.hansudung DESC, c.nam DESC, c.thang DESC " .
    "LIMIT ? OFFSET ?"
);
 $stmt->bind_param('sii', $maKH, $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();

$invoices = [];

while ($row = $result->fetch_assoc()) {
    $invoices[] = [
        'maHD' => intval($row['maHD']),
        'maTT' => intval($row['maTT']),
        'maCSD' => intval($row['maCSD']),
        'thang' => intval($row['thang']),
        'nam' => intval($row['nam']),
        'sodiendatieuthu' => intval($row['sodiendatieuthu']),
        'tongtien' => floatval($row['tongtien']),
        'sotien' => floatval($row['sotien']),
        'trangthai' => $row['trangthai'],
        'paymentStatus' => $row['paymentStatus'],
        'hansudung' => $row['hansudung'],
        'chisocu' => intval($row['chisocu']),
        'chisomoi' => intval($row['chisomoi'])
    ];
}

$stmt->close();

echo json_encode([
    'success' => true,
    'customer' => [
        'maKH' => $customer['maKH'],
        'hovaten' => $customer['hovaten']
    ],
    'invoices' => $invoices,
    'totalAmount' => $totalAmount,
    'page' => $page,
    'perPage' => $perPage,
    'totalPages' => $totalPages,
    'totalInvoices' => $totalInvoices,
    'count' => count($invoices)
]);

$conn->close();
?>
