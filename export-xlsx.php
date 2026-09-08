<?php
require_once __DIR__ . '/require-admin.php';
require 'config.php';

$source = trim($_GET['source'] ?? '');
$rows = [];
$headers = [];
$filename = 'xuat-du-lieu.xlsx';

function bindExportParams(mysqli_stmt $stmt, string $types, array &$params): void
{
    if ($types === '') return;
    $values = [$types];
    foreach ($params as &$param) $values[] = &$param;
    call_user_func_array([$stmt, 'bind_param'], $values);
}

function xmlValue($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function excelCell($value, int $column): string
{
    $reference = '';
    $number = $column + 1;
    while ($number > 0) {
        $remainder = ($number - 1) % 26;
        $reference = chr(65 + $remainder) . $reference;
        $number = intdiv($number - 1, 26);
    }
    $reference .= '{row}';
    $isNumber = is_int($value) || is_float($value) || (is_string($value) && $value !== '' && is_numeric($value));
    if ($isNumber) {
        return '<c r="' . $reference . '"><v>' . xmlValue($value) . '</v></c>';
    }
    return '<c r="' . $reference . '" t="inlineStr"><is><t xml:space="preserve">' . xmlValue($value) . '</t></is></c>';
}

function createStoredZip(array $files): string
{
    $local = '';
    $central = '';
    $offset = 0;
    $count = 0;

    foreach ($files as $name => $content) {
        $name = (string) $name;
        $content = (string) $content;
        $nameLength = strlen($name);
        $contentLength = strlen($content);
        $crc = crc32($content);
        $header = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $contentLength, $contentLength, $nameLength, 0) . $name;
        $local .= $header . $content;

        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0, $crc, $contentLength, $contentLength, $nameLength, 0, 0, 0, 0, 0, $offset) . $name;
        $offset += strlen($header) + $contentLength;
        $count++;
    }

    $centralOffset = strlen($local);
    $centralSize = strlen($central);
    $end = pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, $centralSize, $centralOffset, 0);
    return $local . $central . $end;
}

if ($source === 'customers') {
    $province = preg_replace('/\D/', '', (string) ($_GET['province_code'] ?? ''));
    $ward = preg_replace('/\D/', '', (string) ($_GET['ward_code'] ?? ''));
    $conditions = ["t.quyen = 'khachhang'"];
    $params = [];
    $types = '';
    $electricCode = '';
    if ($province !== '' && strlen($province) <= 2) {
        $provinceLookup = $conn->prepare("SELECT ma_dien_luc FROM don_vi_hanh_chinh WHERE ma_tinh = ? LIMIT 1");
        if ($provinceLookup) {
            $provinceLookup->bind_param('s', $province);
            $provinceLookup->execute();
            $provinceRow = $provinceLookup->get_result()->fetch_assoc();
            $electricCode = strtoupper(trim($provinceRow['ma_dien_luc'] ?? ''));
            $provinceLookup->close();
        }
        if ($electricCode === '') {
            $conditions[] = '1 = 0';
        }
    }
    if ($province !== '' && strlen($province) <= 2) {
        $conditions[] = "t.maKH LIKE CONCAT(?, ?, '%')";
        $types .= 'ss';
        $params[] = $electricCode;
        $params[] = str_pad($province, 2, '0', STR_PAD_LEFT);
    }
    if ($ward !== '' && strlen($ward) <= 5 && $electricCode !== '') {
        $conditions[] = "t.maKH LIKE CONCAT(?, ?, ?, '%')";
        $types .= 'ss';
        $params[] = $electricCode;
        $params[] = str_pad($province, 2, '0', STR_PAD_LEFT);
        $params[] = str_pad($ward, 5, '0', STR_PAD_LEFT);
        $types .= 's';
    }
    $sql = "SELECT t.maKH, t.hovaten, t.email, t.sodienthoai, t.quyen, t.diachi, t.ngaytao FROM taikhoan t WHERE " . implode(' AND ', $conditions) . " ORDER BY t.maKH ASC";
    $stmt = $conn->prepare($sql);
    bindExportParams($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = [$row['maKH'], $row['hovaten'], $row['email'], $row['sodienthoai'], $row['quyen'] === 'admin' ? 'Admin' : 'Khách hàng', $row['diachi'], $row['ngaytao']];
    }
    $stmt->close();
    $headers = ['Mã khách hàng', 'Họ và tên', 'Email', 'Số điện thoại', 'Vai trò', 'Địa chỉ', 'Ngày tạo'];
    $filename = 'khach-hang' . ($ward !== '' ? '-' . $ward : '') . '.xlsx';
} elseif ($source === 'meter') {
    $month = max(1, min(12, intval($_GET['thang'] ?? date('n'))));
    $year = max(2000, intval($_GET['nam'] ?? date('Y')));
    $sql = "SELECT t.maKH, t.hovaten, t.sodienthoai, t.diachi,
                   COALESCE((SELECT c_prev.chisomoi FROM chisodien c_prev
                                         WHERE c_prev.maKH = t.maKH AND (c_prev.nam < ? OR (c_prev.nam = ? AND c_prev.thang < ?))
                                         ORDER BY c_prev.nam DESC, c_prev.thang DESC, c_prev.maCSD DESC LIMIT 1), c.chisocu) AS chisocu,
                   c.chisomoi
            FROM taikhoan t
            LEFT JOIN chisodien c ON c.maKH = t.maKH AND c.thang = ? AND c.nam = ?
            WHERE t.quyen = 'khachhang' ORDER BY t.maKH ASC";
    $stmt = $conn->prepare($sql);
        $stmt->bind_param('iiiii', $year, $year, $month, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $rows[] = [$row['maKH'], $row['hovaten'], $row['sodienthoai'], $row['diachi'], $row['chisocu'], $row['chisomoi'], $month, $year];
    $stmt->close();
    $headers = ['Mã khách hàng', 'Họ và tên', 'Số điện thoại', 'Địa chỉ', "Chỉ số trước kỳ {$month}/{$year}", "Chỉ số kỳ {$month}/{$year}", 'Tháng', 'Năm'];
    $filename = "danh-sach-ho-{$month}-{$year}.xlsx";
} elseif ($source === 'revenue') {
    $from = trim($_GET['from'] ?? '');
    $to = trim($_GET['to'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $conditions = [];
    $params = [];
    $types = '';
    if ($from !== '') { $conditions[] = 'ngaytao >= ?'; $types .= 's'; $params[] = $from . ' 00:00:00'; }
    if ($to !== '') { $conditions[] = 'ngaytao <= ?'; $types .= 's'; $params[] = $to . ' 23:59:59'; }
    if ($status !== '') { $conditions[] = 'trangthai = ?'; $types .= 's'; $params[] = $status; }
    $sql = 'SELECT maHD, maKH, ngaytao, tongtien, trangthai FROM hoadon' . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '') . ' ORDER BY ngaytao DESC, maHD DESC';
    $stmt = $conn->prepare($sql);
    bindExportParams($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $rows[] = [intval($row['maHD']), $row['maKH'], $row['ngaytao'], floatval($row['tongtien']), $row['trangthai'] === 'dathanhtoan' ? 'Đã thanh toán' : 'Chưa thanh toán'];
    $stmt->close();
    $headers = ['Mã hóa đơn', 'Mã khách hàng', 'Ngày tạo', 'Tổng tiền (VNĐ)', 'Trạng thái'];
    $filename = 'doanh-thu.xlsx';
} else {
    http_response_code(400);
    exit('Loại dữ liệu XLSX không hợp lệ.');
}

$rowsXml = '';
foreach (array_merge([$headers], $rows) as $rowIndex => $row) {
    $cells = '';
    foreach ($row as $column => $value) $cells .= excelCell($value, $column);
    $rowsXml .= '<row r="' . ($rowIndex + 1) . '">' . str_replace('{row}', (string) ($rowIndex + 1), $cells) . '</row>';
}

$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"/></sheetViews><sheetData>' . $rowsXml . '</sheetData></worksheet>';
$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Du lieu" sheetId="1" r:id="rId1"/></sheets></workbook>';
$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
$workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>';
$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>';

$xlsx = createStoredZip([
    '[Content_Types].xml' => $contentTypes,
    '_rels/.rels' => $rels,
    'xl/workbook.xml' => $workbook,
    'xl/_rels/workbook.xml.rels' => $workbookRels,
    'xl/worksheets/sheet1.xml' => $sheet
]);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($xlsx));
echo $xlsx;
$conn->close();
