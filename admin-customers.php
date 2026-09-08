<?php
require_once __DIR__ . '/require-admin.php';
require 'config.php';
header('Content-Type: application/json; charset=utf-8');

$maKH = isset($_GET['maKH']) ? trim($_GET['maKH']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$province_code = preg_replace('/\D/', '', (string) ($_GET['province_code'] ?? ''));
$ward_code = preg_replace('/\D/', '', (string) ($_GET['ward_code'] ?? ''));
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 10;
$per_page = min($per_page, 100);
$export = isset($_GET['export']) && $_GET['export'] === 'csv';
$stats_only = isset($_GET['stats_only']) && $_GET['stats_only'] === '1';

if ($maKH !== '') {
    $stmt = $conn->prepare("SELECT maKH, hovaten, email, sodienthoai, quyen, trangthai, diachi, ngaytao FROM taikhoan WHERE maKH = ? LIMIT 1");
    $stmt->bind_param('s', $maKH);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
    $stmt->close();

    if (!$customer) {
        echo json_encode(['success' => false, 'error' => 'Không tìm thấy khách hàng.']);
        $conn->close();
        exit;
    }

    echo json_encode(['success' => true, 'customer' => $customer]);
    $conn->close();
    exit;
}

if ($stats_only) {
    // Return only aggregate statistics without fetching all customer details
    $result = $conn->query("SELECT COUNT(*) as total FROM taikhoan");
    $countRow = $result->fetch_assoc();
    $total = intval($countRow['total']);
    
    $result = $conn->query(
        "SELECT " .
        "COUNT(t.maKH) as total_customers, " .
        "COALESCE(SUM(c.dntieuthu), 0) as total_kwh, " .
        "COALESCE(AVG(c.dntieuthu), 0) as avg_kwh " .
        "FROM taikhoan t LEFT JOIN chisodien c ON t.maKH = c.maKH"
    );
    $stats = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'total_customers' => intval($stats['total_customers']),
        'total_kwh' => intval($stats['total_kwh']),
        'avg_kwh' => round(floatval($stats['avg_kwh']), 2)
    ]);
    $conn->close();
    exit;
}

$conditions = [];
$bindValues = [];
$bindTypes = '';
$conditions[] = "t.quyen = 'khachhang'";

$electricCode = '';
if ($province_code !== '' && strlen($province_code) <= 2) {
    $provinceLookup = $conn->prepare("SELECT ma_dien_luc FROM don_vi_hanh_chinh WHERE ma_tinh = ? LIMIT 1");
    if ($provinceLookup) {
        $provinceLookup->bind_param('s', $province_code);
        $provinceLookup->execute();
        $provinceRow = $provinceLookup->get_result()->fetch_assoc();
        $electricCode = strtoupper(trim($provinceRow['ma_dien_luc'] ?? ''));
        $provinceLookup->close();
    }
    if ($electricCode === '') {
        $conditions[] = '1 = 0';
    }
}

if ($province_code !== '' && strlen($province_code) <= 2) {
    $conditions[] = "t.maKH LIKE CONCAT(?, ?, '%')";
    $bindTypes .= 's';
    $bindValues[] = $electricCode;
    $bindTypes .= 's';
    $bindValues[] = str_pad($province_code, 2, '0', STR_PAD_LEFT);
}
if ($ward_code !== '' && strlen($ward_code) <= 5 && $electricCode !== '') {
    $conditions[] = "t.maKH LIKE CONCAT(?, ?, ?, '%')";
    $bindTypes .= 's';
    $bindValues[] = $electricCode;
    $bindTypes .= 's';
    $bindValues[] = str_pad($province_code, 2, '0', STR_PAD_LEFT);
    $bindTypes .= 's';
    $bindValues[] = str_pad($ward_code, 5, '0', STR_PAD_LEFT);
}

$searchParam = '';
if ($search !== '') {
    $conditions[] = "(t.maKH LIKE ? OR t.hovaten LIKE ? OR t.email LIKE ? OR t.sodienthoai LIKE ? OR t.diachi LIKE ?)";
    $searchParam = '%' . $search . '%';
    $bindTypes .= 'sssss';
    array_push($bindValues, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
}
$searchCondition = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$bind = function (mysqli_stmt $stmt, string $types, array &$values): void {
    if ($types === '') return;
    $params = [$types];
    foreach ($values as $key => &$value) $params[] = &$value;
    call_user_func_array([$stmt, 'bind_param'], $params);
};

// Get the filtered total so pagination matches the search results.
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM taikhoan t" . $searchCondition);
$countValues = $bindValues;
$bind($countStmt, $bindTypes, $countValues);
$countStmt->execute();
$countRow = $countStmt->get_result()->fetch_assoc();
$countStmt->close();
$total = intval($countRow['total']);

$selectSql = "SELECT t.maKH, t.hovaten, t.email, t.sodienthoai, t.quyen, t.trangthai, t.diachi, t.ngaytao " .
    "FROM taikhoan t" . $searchCondition . " ORDER BY t.maKH ASC";
if (!$export) $selectSql .= " LIMIT ? OFFSET ?";
$stmt = $conn->prepare($selectSql);
$selectTypes = $bindTypes;
$selectValues = $bindValues;
if (!$export) {
    $selectTypes .= 'ii';
    $selectValues[] = $per_page;
    $selectValues[] = ($page - 1) * $per_page;
}
$bind($stmt, $selectTypes, $selectValues);
$stmt->execute();
$result = $stmt->get_result();
$customers = [];
while ($row = $result->fetch_assoc()) {
    $customers[] = $row;
}
$stmt->close();

if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="khach-hang-khu-vuc.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, "\xEF\xBB\xBF");
    $delimiter = ';';
    fputcsv($output, ['Mã khách hàng', 'Họ và tên', 'Email', 'Số điện thoại', 'Vai trò', 'Địa chỉ', 'Ngày tạo'], $delimiter);
    foreach ($customers as $customer) {
        fputcsv($output, [
            $customer['maKH'],
            $customer['hovaten'],
            $customer['email'],
            $customer['sodienthoai'],
            $customer['quyen'] === 'admin' ? 'Admin' : 'Khách hàng',
            $customer['diachi'],
            $customer['ngaytao']
        ], $delimiter);
    }
    fclose($output);
    $conn->close();
    exit;
}

echo json_encode([
    'success' => true,
    'customers' => $customers,
    'total' => $total,
    'page' => $page,
    'per_page' => $per_page,
    'total_pages' => ceil($total / $per_page)
]);
$conn->close();
