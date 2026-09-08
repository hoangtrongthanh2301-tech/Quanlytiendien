<?php
require_once __DIR__ . '/require-admin.php';
require 'config.php';
header('Content-Type: application/json; charset=utf-8');

$thang = isset($_GET['thang']) ? intval($_GET['thang']) : 0;
$nam = isset($_GET['nam']) ? intval($_GET['nam']) : 0;
$maKH = isset($_GET['maKH']) ? trim($_GET['maKH']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['per_page']) ? max(1, min(50, intval($_GET['per_page']))) : 10;
$offset = ($page - 1) * $perPage;

if ($thang < 1 || $thang > 12 || $nam < 2000 || $nam > 2100) {
    $thang = intval(date('n'));
    $nam = intval(date('Y'));
}

$whereSql = "WHERE t.quyen = 'khachhang'";
$params = [];
$types = '';
if ($maKH !== '') {
    $maKH = strtoupper($maKH);
    // keep maKH uppercased; condition will be appended to $whereSql after escaping
}

function bindStatementParams(mysqli_stmt $stmt, string $types, array &$params): bool {
    $bindNames = [$types];
    foreach ($params as $key => &$value) {
        $bindNames[] = &$value;
    }
    return call_user_func_array([$stmt, 'bind_param'], $bindNames);
}

$countSql = "SELECT COUNT(*) AS total FROM taikhoan t " . $whereSql;
$stmtCount = $conn->prepare($countSql);
if (!$stmtCount) {
    echo json_encode(['success' => false, 'error' => 'Lỗi chuẩn bị truy vấn tổng số hộ: ' . $conn->error]);
    exit;
}
if ($params) {
    if (!bindStatementParams($stmtCount, $types, $params)) {
        echo json_encode(['success' => false, 'error' => 'Lỗi bind tham số tổng số hộ: ' . $stmtCount->error]);
        exit;
    }
}
$stmtCount->execute();
$countResult = $stmtCount->get_result();
$countRow = $countResult->fetch_assoc();
$total = intval($countRow['total']);
$stmtCount->close();

if ($maKH !== '' && $total === 0) {
    echo json_encode(['success' => false, 'error' => 'Mã khách hàng không tồn tại.']);
    exit;
}

$escapedThang = intval($thang);
$escapedNam = intval($nam);
$escapedOffset = intval($offset);
$escapedPerPage = intval($perPage);
$escapedMaKH = $maKH !== '' ? $conn->real_escape_string($maKH) : '';

$latestPeriod = ['thang' => null, 'nam' => null];
$latestStmt = $conn->prepare(
    'SELECT thang, nam FROM chisodien
     WHERE (nam < ? OR (nam = ? AND thang < ?))
     ORDER BY nam DESC, thang DESC, maCSD DESC LIMIT 1'
);
if ($latestStmt) {
    $latestStmt->bind_param('iii', $nam, $nam, $thang);
    $latestStmt->execute();
    $latestRow = $latestStmt->get_result()->fetch_assoc();
    if ($latestRow) {
        $latestPeriod = ['thang' => intval($latestRow['thang']), 'nam' => intval($latestRow['nam'])];
    }
    $latestStmt->close();
}

if ($escapedMaKH !== '') {
    $whereSql .= " AND UPPER(t.maKH) = '" . $escapedMaKH . "'";
}

$sql = "SELECT t.maKH, t.hovaten, t.diachi, t.sodienthoai,
          (SELECT maCSD FROM chisodien c1 WHERE c1.maKH = t.maKH AND c1.thang = $escapedThang AND c1.nam = $escapedNam ORDER BY c1.maCSD DESC LIMIT 1) AS current_maCSD,
          (SELECT chisocu FROM chisodien c2 WHERE c2.maKH = t.maKH AND c2.thang = $escapedThang AND c2.nam = $escapedNam ORDER BY c2.maCSD DESC LIMIT 1) AS existing_chisocu,
          (SELECT chisomoi FROM chisodien c3 WHERE c3.maKH = t.maKH AND c3.thang = $escapedThang AND c3.nam = $escapedNam ORDER BY c3.maCSD DESC LIMIT 1) AS existing_chisomoi,
          (SELECT c4.chisomoi FROM chisodien c4 WHERE c4.maKH = t.maKH AND (c4.nam < $escapedNam OR (c4.nam = $escapedNam AND c4.thang < $escapedThang)) ORDER BY c4.nam DESC, c4.thang DESC, c4.maCSD DESC LIMIT 1) AS prev_chisomoi,
          (SELECT c5.thang FROM chisodien c5 WHERE c5.maKH = t.maKH AND (c5.nam < $escapedNam OR (c5.nam = $escapedNam AND c5.thang < $escapedThang)) ORDER BY c5.nam DESC, c5.thang DESC, c5.maCSD DESC LIMIT 1) AS prev_thang,
          (SELECT c6.nam FROM chisodien c6 WHERE c6.maKH = t.maKH AND (c6.nam < $escapedNam OR (c6.nam = $escapedNam AND c6.thang < $escapedThang)) ORDER BY c6.nam DESC, c6.thang DESC, c6.maCSD DESC LIMIT 1) AS prev_nam
         FROM taikhoan t
         " . $whereSql . "
         ORDER BY t.maKH
         LIMIT $escapedOffset, $escapedPerPage";

$result = $conn->query($sql);
if (!$result) {
    echo json_encode(['success' => false, 'error' => 'Lỗi thực thi truy vấn: ' . $conn->error]);
    exit;
}

$entries = [];
while ($row = $result->fetch_assoc()) {
    $entries[] = [
        'maKH' => $row['maKH'],
        'hovaten' => $row['hovaten'],
        'diachi' => $row['diachi'],
        'sodienthoai' => $row['sodienthoai'],
        'current_maCSD' => $row['current_maCSD'] !== null ? intval($row['current_maCSD']) : null,
        'existing_chisocu' => $row['existing_chisocu'] !== null ? intval($row['existing_chisocu']) : null,
        'existing_chisomoi' => $row['existing_chisomoi'] !== null ? intval($row['existing_chisomoi']) : null,
        'prev_chisomoi' => $row['prev_chisomoi'] !== null ? intval($row['prev_chisomoi']) : null,
        'prev_thang' => $row['prev_thang'] !== null ? intval($row['prev_thang']) : null,
        'prev_nam' => $row['prev_nam'] !== null ? intval($row['prev_nam']) : null,
        'thang' => $thang,
        'nam' => $nam,
    ];
}

echo json_encode([
    'success' => true,
    'entries' => $entries,
    'total' => $total,
    'thang' => $thang,
    'nam' => $nam,
    'latest_period' => $latestPeriod,
    'page' => $page,
    'per_page' => $perPage
]);
$conn->close();
