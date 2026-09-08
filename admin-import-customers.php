<?php
require_once __DIR__ . '/require-admin.php';
require 'config.php';
require 'customer-code-validation.php';
header('Content-Type: application/json; charset=utf-8');

if (function_exists('set_time_limit')) {
    @set_time_limit(0);
}
@ini_set('max_execution_time', '0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Phải dùng phương thức POST.']);
    exit;
}

if (empty($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Vui lòng tải lên tệp CSV, SQL hoặc XLSX hợp lệ.']);
    exit;
}

$file = $_FILES['import_file'];
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

switch ($extension) {
    case 'csv':
        $result = importCsv($file['tmp_name']);
        break;
    case 'xlsx':
        $result = importXlsx($file['tmp_name']);
        break;
    case 'sql':
        $result = importSql($file['tmp_name']);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Chỉ hỗ trợ tệp CSV, SQL hoặc XLSX cho import khách hàng.']);
        $conn->close();
        exit;
}

$conn->close();
echo json_encode($result);

function importCsv(string $filePath): array
{
    $handle = fopen($filePath, 'r');
    if ($handle === false) {
        return ['success' => false, 'error' => 'Không thể đọc tệp CSV.'];
    }

    $header = null;
    $rowCount = 0;
    $imported = 0;
    $skipped = 0;
    $errors = [];
    $seenKeys = [];

    while (($row = fgetcsv($handle)) !== false) {
        if ($row === [null] || empty(array_filter($row, fn($value) => $value !== null && $value !== ''))) {
            continue;
        }

        if ($header === null) {
            $header = array_map('trim', $row);
            continue;
        }

        $rowCount++;
        $data = array_combine($header, $row);
        if ($data === false) {
            $errors[] = "Dòng {$rowCount} sai định dạng.";
            $skipped++;
            continue;
        }

        $result = processCustomerRow($data, $rowCount, $seenKeys);
        if ($result['skipped']) {
            $skipped++;
            $errors[] = $result['error'];
            continue;
        }

        if ($result['imported']) {
            $imported++;
        }
    }

    fclose($handle);

    return [
        'success' => $imported > 0,
        'rows_processed' => $rowCount,
        'rows_imported' => $imported,
        'rows_skipped' => $skipped,
        'errors' => $errors,
    ];
}

function importXlsx(string $filePath): array
{
    if (!class_exists('ZipArchive')) {
        return ['success' => false, 'error' => 'PHP cần bật extension Zip để xử lý XLSX.'];
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return ['success' => false, 'error' => 'Không thể mở tệp XLSX.'];
    }

    $sharedStrings = [];
    if (($index = $zip->locateName('xl/sharedStrings.xml')) !== false) {
        $sharedStringsXml = $zip->getFromIndex($index);
        $sharedStrings = parseXlsxSharedStrings($sharedStringsXml);
    }

    $sheetIndex = $zip->locateName('xl/worksheets/sheet1.xml');
    if ($sheetIndex === false) {
        $zip->close();
        return ['success' => false, 'error' => 'Không tìm thấy sheet đầu tiên trong XLSX.'];
    }

    $sheetXml = $zip->getFromIndex($sheetIndex);
    $zip->close();

    $rows = parseXlsxSheet($sheetXml, $sharedStrings);
    if (empty($rows)) {
        return [
            'success' => false,
            'rows_processed' => 0,
            'rows_imported' => 0,
            'rows_skipped' => 0,
            'errors' => ['Không tìm thấy dữ liệu trong tệp XLSX.'],
        ];
    }

    $header = array_map('trim', array_shift($rows));
    $rowCount = 0;
    $imported = 0;
    $skipped = 0;
    $errors = [];
    $seenKeys = [];

    foreach ($rows as $row) {
        if (empty(array_filter($row, fn($value) => $value !== null && $value !== ''))) {
            continue;
        }

        $rowCount++;
        $data = array_combine($header, $row);
        if ($data === false) {
            $errors[] = "Dòng {$rowCount} sai định dạng.";
            $skipped++;
            continue;
        }

        $result = processCustomerRow($data, $rowCount, $seenKeys);
        if ($result['skipped']) {
            $skipped++;
            $errors[] = $result['error'];
            continue;
        }

        if ($result['imported']) {
            $imported++;
        }
    }

    return [
        'success' => $imported > 0,
        'rows_processed' => $rowCount,
        'rows_imported' => $imported,
        'rows_skipped' => $skipped,
        'errors' => $errors,
    ];
}

function importSql(string $filePath): array
{
    $contents = file_get_contents($filePath);
    if ($contents === false) {
        return ['success' => false, 'error' => 'Không thể đọc tệp SQL.'];
    }

    $parts = preg_split('/;\s*(?=INSERT\s+INTO)/i', $contents);
    $rowCount = 0;
    $imported = 0;
    $skipped = 0;
    $errors = [];
    $seenKeys = [];

    foreach ($parts as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }

        if (stripos($statement, 'INSERT INTO `taikhoan`') === false && stripos($statement, 'INSERT INTO taikhoan') === false) {
            continue;
        }

        try {
            $rows = parseSqlInsertStatement($statement);
        } catch (Throwable $exception) {
            $errors[] = 'Lỗi phân tích SQL: ' . $exception->getMessage();
            continue;
        }

        foreach ($rows as $row) {
            $rowCount++;
            $result = processCustomerRow($row, $rowCount, $seenKeys);
            if ($result['skipped']) {
                $skipped++;
                $errors[] = $result['error'];
                continue;
            }
            if ($result['imported']) {
                $imported++;
            }
        }
    }

    return [
        'success' => $imported > 0,
        'rows_processed' => $rowCount,
        'rows_imported' => $imported,
        'rows_skipped' => $skipped,
        'errors' => $errors,
    ];
}

function processCustomerRow(array $data, int $rowCount, array &$seenKeys): array
{
    global $conn;

    $maKH = trim($data['maKH'] ?? $data['MAKH'] ?? '');
    $hovaten = trim($data['hovaten'] ?? $data['HOVATEN'] ?? '');
    $email = trim($data['email'] ?? $data['EMAIL'] ?? '');
    $sodienthoai = trim($data['sodienthoai'] ?? $data['SODIENTHOAI'] ?? '');
    $matkhau = trim($data['matkhau'] ?? $data['MATKHAU'] ?? '');
    $quyen = trim($data['quyen'] ?? $data['QUYEN'] ?? 'khachhang');
    $trangthai = trim($data['trangthai'] ?? $data['TRANGTHAI'] ?? 'hoatdong');
    $diachi = trim($data['diachi'] ?? $data['DIACHI'] ?? '');

    if (!$maKH || !$hovaten || !$email || !$sodienthoai || !$matkhau) {
        return ['imported' => false, 'skipped' => true, 'error' => "Dòng {$rowCount} thiếu trường bắt buộc."];
    }

    $codeValidation = validateCustomerCode($conn, $maKH);
    if (!$codeValidation['valid']) {
        return ['imported' => false, 'skipped' => true, 'error' => "Dòng {$rowCount}: {$codeValidation['error']}" ];
    }
    $maKH = $codeValidation['maKH'];

    $key = strtolower($maKH . '|' . $email . '|' . $sodienthoai);
    if (isset($seenKeys[$key])) {
        return ['imported' => false, 'skipped' => true, 'error' => "Dòng {$rowCount} trùng dữ liệu với dòng {$seenKeys[$key]}."];
    }
    $seenKeys[$key] = $rowCount;

    $stmt = $conn->prepare("SELECT maKH FROM taikhoan WHERE maKH = ? OR email = ? OR sodienthoai = ? LIMIT 1");
    $stmt->bind_param('sss', $maKH, $email, $sodienthoai);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $stmt->close();
        return ['imported' => false, 'skipped' => true, 'error' => "Dòng {$rowCount} đã tồn tại mã KH/email/số điện thoại trong hệ thống."];
    }
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO taikhoan (maKH, hovaten, email, sodienthoai, matkhau, quyen, trangthai, diachi) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssssssss', $maKH, $hovaten, $email, $sodienthoai, $matkhau, $quyen, $trangthai, $diachi);
    try {
        $success = $stmt->execute();
        $error = $stmt->error;
    } catch (mysqli_sql_exception $exception) {
        $success = false;
        $error = $exception->getMessage();
    }
    $stmt->close();

    if (!$success) {
        return ['imported' => false, 'skipped' => true, 'error' => "Dòng {$rowCount} không lưu được: {$error}"];
    }

    return ['imported' => true, 'skipped' => false, 'error' => ''];
}

function parseSqlInsertStatement(string $sql): array
{
    if (!preg_match('/INSERT\s+INTO\s+[`\"]?taikhoan[`\"]?\s*\(([^)]+)\)\s*VALUES\s*(.+)$/is', trim($sql), $matches)) {
        throw new RuntimeException('Câu lệnh SQL không phải INSERT INTO taikhoan hợp lệ.');
    }

    $columns = array_map(fn($column) => trim($column, "` \t\n\r\0\x0B\""), explode(',', $matches[1]));
    $valuesSection = trim($matches[2]);

    $tuples = splitSqlTuples($valuesSection);
    $rows = [];
    foreach ($tuples as $tuple) {
        $values = parseSqlTuple($tuple);
        if (count($values) !== count($columns)) {
            continue;
        }
        $rows[] = array_combine($columns, $values);
    }

    return $rows;
}

function splitSqlTuples(string $valuesSection): array
{
    $tuples = [];
    $length = strlen($valuesSection);
    $depth = 0;
    $inString = false;
    $escape = false;
    $current = '';

    for ($i = 0; $i < $length; $i++) {
        $char = $valuesSection[$i];

        if ($escape) {
            $current .= $char;
            $escape = false;
            continue;
        }

        if ($char === '\\') {
            $current .= $char;
            $escape = true;
            continue;
        }

        if ($char === "'" || $char === '"') {
            $current .= $char;
            if ($inString === false) {
                $inString = $char;
            } elseif ($inString === $char) {
                $inString = false;
            }
            continue;
        }

        if ($inString !== false) {
            $current .= $char;
            continue;
        }

        if ($char === '(') {
            $depth++;
            $current .= $char;
            continue;
        }

        if ($char === ')') {
            $depth--;
            $current .= $char;
            if ($depth === 0) {
                $tuples[] = trim($current);
                $current = '';
            }
            continue;
        }

        if ($depth > 0) {
            $current .= $char;
        }
    }

    return $tuples;
}

function parseSqlTuple(string $tuple): array
{
    $tuple = trim($tuple);
    if (str_starts_with($tuple, '(') && str_ends_with($tuple, ')')) {
        $tuple = substr($tuple, 1, -1);
    }

    $length = strlen($tuple);
    $inString = false;
    $escape = false;
    $current = '';
    $values = [];

    for ($i = 0; $i < $length; $i++) {
        $char = $tuple[$i];
        if ($escape) {
            $current .= $char;
            $escape = false;
            continue;
        }

        if ($char === '\\') {
            $escape = true;
            $current .= $char;
            continue;
        }

        if ($char === "'" || $char === '"') {
            $current .= $char;
            if ($inString === false) {
                $inString = $char;
            } elseif ($inString === $char) {
                $inString = false;
            }
            continue;
        }

        if ($inString !== false) {
            $current .= $char;
            continue;
        }

        if ($char === ',') {
            $values[] = parseSqlValue($current);
            $current = '';
            continue;
        }

        $current .= $char;
    }

    if ($current !== '') {
        $values[] = parseSqlValue($current);
    }

    return $values;
}

function parseSqlValue(string $value)
{
    $value = trim($value);
    if ($value === '' || strcasecmp($value, 'NULL') === 0) {
        return null;
    }
    if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
        $value = substr($value, 1, -1);
        return str_replace(["\\'", "\\\\"], ["'", "\\"], $value);
    }
    if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
        $value = substr($value, 1, -1);
        return str_replace(['\\"', "\\\\"], ['"', "\\"], $value);
    }
    return $value;
}

function parseXlsxSharedStrings(string $xml): array
{
    $sharedStrings = [];
    $xml = simplexml_load_string($xml);
    if ($xml === false || !isset($xml->si)) {
        return $sharedStrings;
    }
    foreach ($xml->si as $si) {
        $sharedStrings[] = getXlsxNodeValue($si);
    }
    return $sharedStrings;
}

function parseXlsxSheet(string $sheetXml, array $sharedStrings): array
{
    $xml = simplexml_load_string($sheetXml);
    if ($xml === false || !isset($xml->sheetData->row)) {
        return [];
    }
    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $cell) {
            $ref = (string) $cell['r'];
            $column = preg_replace('/\d+$/', '', $ref);
            $index = columnLetterToIndex($column);
            $value = isset($cell->v) ? (string) $cell->v : '';
            if ((string) $cell['t'] === 's') {
                $value = $sharedStrings[intval($value)] ?? $value;
            }
            $cells[$index] = $value;
        }
        ksort($cells, SORT_NUMERIC);
        $rows[] = array_values($cells);
    }
    return $rows;
}

function getXlsxNodeValue($node): string
{
    if (isset($node->t)) {
        return (string) $node->t;
    }
    $value = '';
    foreach ($node->children() as $child) {
        $value .= getXlsxNodeValue($child);
    }
    return $value;
}

function columnLetterToIndex(string $column): int
{
    $index = 0;
    $length = strlen($column);
    for ($i = 0; $i < $length; $i++) {
        $index = $index * 26 + (ord($column[$i]) - ord('A') + 1);
    }
    return $index - 1;
}
