<?php

namespace App\Services;

use App\Repositories\ChiSoDienRepository;
use App\Core\Database;

require_once __DIR__ . '/../../blockchain.php';

class ImportService
{
    private ChiSoDienRepository $repository;
    private Database $database;

    public function __construct(ChiSoDienRepository $repository, Database $database)
    {
        $this->repository = $repository;
        $this->database = $database;
    }

    public function importFile(string $filePath, ?string $fileName = null, int $chunkSize = 200): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException('Tệp import không tồn tại.');
        }

        $extension = strtolower(pathinfo($fileName ?? $filePath, PATHINFO_EXTENSION));
        switch ($extension) {
            case 'csv':
            case 'txt':
                return $this->importCsv($filePath, $chunkSize);
            case 'sql':
                return $this->importSql($filePath, $chunkSize);
            case 'xlsx':
                return $this->importXlsx($filePath, $chunkSize);
            default:
                throw new \RuntimeException('Chỉ hỗ trợ tệp CSV, SQL hoặc XLSX.');
        }
    }

    public function importCsv(string $filePath, int $chunkSize = 200): array
    {
        $file = new \SplFileObject($filePath, 'r');
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(',');

        $header = null;
        $rowCount = 0;
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $batch = [];
        $importedPeriods = [];

        while (!$file->eof()) {
            $row = $file->fgetcsv();
            if ($row === false || $row === [null] || empty(array_filter($row, fn($value) => $value !== null && $value !== ''))) {
                continue;
            }

            if ($header === null) {
                $header = array_map('trim', $row);
                continue;
            }

            $rowCount++;
            $record = $this->normalizeCsvRow($row, $header);
            if ($record === null) {
                $errors[] = "Dòng {$rowCount} sai định dạng hoặc thiếu trường bắt buộc.";
                $skipped++;
                continue;
            }

            $batch[] = $record;
            $importedPeriods["{$record['thang']}-{$record['nam']}"] = [
                'thang' => $record['thang'],
                'nam' => $record['nam'],
            ];

            if (count($batch) >= $chunkSize) {
                $newRows = $this->getNewImportRows($batch);
                if ($this->repository->saveBatch($batch)) {
                    $imported += count($batch);
                    $this->createBlockchainForImportedRows($newRows);
                } else {
                    $errors[] = "Lỗi ghi dữ liệu vào MySQL sau {$rowCount} dòng.";
                }
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $newRows = $this->getNewImportRows($batch);
            if ($this->repository->saveBatch($batch)) {
                $imported += count($batch);
                $this->createBlockchainForImportedRows($newRows);
            } else {
                $errors[] = 'Lỗi ghi dữ liệu vào MySQL sau batch cuối cùng.';
            }
        }

        return [
            'success' => empty($errors),
            'rows_processed' => $rowCount,
            'rows_imported' => $imported,
            'rows_skipped' => $skipped,
            'errors' => $errors,
            'imported_periods' => array_values($importedPeriods),
        ];
    }

    public function importSql(string $filePath, int $chunkSize = 200): array
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Không thể đọc tệp SQL.');
        }

        $statement = '';
        $rowCount = 0;
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $batch = [];
        $importedPeriods = [];

        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line === false) {
                break;
            }

            $statement .= $line;
            if (strpos($line, ';') === false) {
                continue;
            }

            $parts = explode(';', $statement);
            $statement = array_pop($parts);

            foreach ($parts as $part) {
                $sql = trim($part);
                if ($sql === '') {
                    continue;
                }

                if (stripos($sql, 'INSERT INTO `chisodien`') !== false || stripos($sql, 'INSERT INTO chisodien') !== false) {
                    try {
                        $rows = $this->parseSqlInsertStatement($sql);
                        foreach ($rows as $row) {
                            $rowCount++;
                            $record = $this->normalizeImportRow($row);
                            if ($record === null) {
                                $errors[] = "Dòng SQL #{$rowCount} sai định dạng hoặc thiếu trường bắt buộc.";
                                $skipped++;
                                continue;
                            }

                            $batch[] = $record;
                            $importedPeriods["{$record['thang']}-{$record['nam']}"] = [
                                'thang' => $record['thang'],
                                'nam' => $record['nam'],
                            ];

                            if (count($batch) >= $chunkSize) {
                                $newRows = $this->getNewImportRows($batch);
                                if ($this->repository->saveBatch($batch)) {
                                    $imported += count($batch);
                                    $this->createBlockchainForImportedRows($newRows);
                                } else {
                                    $errors[] = "Lỗi ghi dữ liệu vào MySQL sau {$rowCount} dòng SQL.";
                                }
                                $batch = [];
                            }
                        }
                    } catch (\Throwable $exception) {
                        $errors[] = 'Lỗi phân tích SQL: ' . $exception->getMessage();
                    }
                }
            }
        }

        fclose($handle);

        if (!empty($batch)) {
            $newRows = $this->getNewImportRows($batch);
            if ($this->repository->saveBatch($batch)) {
                $imported += count($batch);
                $this->createBlockchainForImportedRows($newRows);
            } else {
                $errors[] = 'Lỗi ghi dữ liệu vào MySQL sau batch SQL cuối cùng.';
            }
        }

        return [
            'success' => empty($errors),
            'rows_processed' => $rowCount,
            'rows_imported' => $imported,
            'rows_skipped' => $skipped,
            'errors' => $errors,
            'imported_periods' => array_values($importedPeriods),
        ];
    }

    public function importXlsx(string $filePath, int $chunkSize = 200): array
    {
        if (!class_exists('\ZipArchive')) {
            throw new \RuntimeException('Không hỗ trợ XLSX: cần bật extension Zip trong PHP.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException('Không thể mở tệp XLSX.');
        }

        $sharedStrings = [];
        if (($index = $zip->locateName('xl/sharedStrings.xml')) !== false) {
            $sharedStringsXml = $zip->getFromIndex($index);
            $sharedStrings = $this->parseXlsxSharedStrings($sharedStringsXml);
        }

        $sheetPath = 'xl/worksheets/sheet1.xml';
        if (($index = $zip->locateName($sheetPath)) === false) {
            throw new \RuntimeException('Không tìm thấy sheet đầu tiên trong tệp XLSX.');
        }

        $sheetXml = $zip->getFromIndex($index);
        $zip->close();

        $rows = $this->parseXlsxSheet($sheetXml, $sharedStrings);
        if (empty($rows)) {
            return [
                'success' => false,
                'rows_processed' => 0,
                'rows_imported' => 0,
                'rows_skipped' => 0,
                'errors' => ['Không tìm thấy dữ liệu trong tệp XLSX.'],
                'imported_periods' => [],
            ];
        }

        $header = array_map('trim', array_shift($rows));
        $rowCount = 0;
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $batch = [];
        $importedPeriods = [];

        foreach ($rows as $row) {
            if (empty(array_filter($row, fn($value) => $value !== null && $value !== ''))) {
                continue;
            }

            $rowCount++;
            $record = $this->normalizeCsvRow($row, $header);
            if ($record === null) {
                $errors[] = "Dòng {$rowCount} sai định dạng hoặc thiếu trường bắt buộc.";
                $skipped++;
                continue;
            }

            $batch[] = $record;
            $importedPeriods["{$record['thang']}-{$record['nam']}"] = [
                'thang' => $record['thang'],
                'nam' => $record['nam'],
            ];

            if (count($batch) >= $chunkSize) {
                $newRows = $this->getNewImportRows($batch);
                if ($this->repository->saveBatch($batch)) {
                    $imported += count($batch);
                    $this->createBlockchainForImportedRows($newRows);
                } else {
                    $errors[] = "Lỗi ghi dữ liệu vào MySQL sau {$rowCount} dòng XLSX.";
                }
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $newRows = $this->getNewImportRows($batch);
            if ($this->repository->saveBatch($batch)) {
                $imported += count($batch);
                $this->createBlockchainForImportedRows($newRows);
            } else {
                $errors[] = 'Lỗi ghi dữ liệu vào MySQL sau batch XLSX cuối cùng.';
            }
        }

        return [
            'success' => empty($errors),
            'rows_processed' => $rowCount,
            'rows_imported' => $imported,
            'rows_skipped' => $skipped,
            'errors' => $errors,
            'imported_periods' => array_values($importedPeriods),
        ];
    }

    private function getNewImportRows(array $rows): array
    {
        $existingMap = $this->repository->getExistingUniqueKeyMap($rows);
        $newRows = [];

        foreach ($rows as $row) {
            $key = sprintf('%s-%d-%d', $row['maKH'], intval($row['thang']), intval($row['nam']));
            if (!isset($existingMap[$key])) {
                $newRows[] = $row;
            }
        }

        return $newRows;
    }

    private function createBlockchainForImportedRows(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $records = $this->repository->getByUniqueKeys($rows);
        $connection = $this->database->getConnection();

        foreach ($records as $record) {
            createBlockchainRecord(
                $connection,
                intval($record['maCSD']),
                $record['maKH'],
                intval($record['chisocu']),
                intval($record['chisomoi']),
                intval($record['thang']),
                intval($record['nam']),
                'import'
            );
        }
    }

    private function parseSqlInsertStatement(string $sql): array
    {
        if (!preg_match('/INSERT\s+INTO\s+[`\"]?chisodien[`\"]?\s*\(([^)]+)\)\s*VALUES\s*(.+)$/is', trim($sql), $matches)) {
            throw new \RuntimeException('Câu lệnh SQL không phải INSERT INTO chisodien hợp lệ.');
        }

        $columns = array_map(fn($column) => trim($column, "` \t\n\r\0\x0B\""), explode(',', $matches[1]));
        $valuesSection = trim($matches[2]);

        $tuples = $this->splitSqlTuples($valuesSection);
        $rows = [];
        foreach ($tuples as $tuple) {
            $values = $this->parseSqlTuple($tuple);
            if (count($values) !== count($columns)) {
                continue;
            }
            $rows[] = array_combine($columns, $values);
        }

        return $rows;
    }

    private function splitSqlTuples(string $valuesSection): array
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

    private function parseSqlTuple(string $tuple): array
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
                $values[] = $this->parseSqlValue($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if ($current !== '') {
            $values[] = $this->parseSqlValue($current);
        }

        return $values;
    }

    private function parseSqlValue(string $value)
    {
        $value = trim($value);
        if ($value === '' || strcasecmp($value, 'NULL') === 0) {
            return null;
        }

        if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
            $value = substr($value, 1, -1);
            return str_replace(["\\'", "\\\\"], ['\'', '\\'], $value);
        }

        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = substr($value, 1, -1);
            return str_replace(['\\"', "\\\\"], ['"', '\\'], $value);
        }

        return $value;
    }

    private function normalizeCsvRow(array $row, array $header): ?array
    {
        $map = array_combine($header, $row);
        if ($map === false) {
            return null;
        }

        return $this->normalizeImportRow($map);
    }

    private function normalizeImportRow(array $map): ?array
    {
        $maKH = trim($map['maKH'] ?? $map['MAKH'] ?? '');
        $chisocu = isset($map['chisocu']) ? intval($map['chisocu']) : null;
        $chisomoi = isset($map['chisomoi']) ? intval($map['chisomoi']) : null;
        $thang = isset($map['thang']) ? intval($map['thang']) : null;
        $nam = isset($map['nam']) ? intval($map['nam']) : null;
        $ngaynhap = trim($map['ngaynhap'] ?? $map['ngay_nhap'] ?? $map['ngaynhap'] ?? date('Y-m-d'));

        if ($maKH === '' || $chisocu === null || $chisomoi === null || $thang === null || $nam === null) {
            return null;
        }

        if ($chisomoi < $chisocu) {
            return null;
        }

        return [
            'maKH' => $maKH,
            'chisocu' => $chisocu,
            'chisomoi' => $chisomoi,
            'thang' => $thang,
            'nam' => $nam,
            'ngaynhap' => $ngaynhap,
        ];
    }

    private function parseXlsxSharedStrings(string $sharedStringsXml): array
    {
        $sharedStrings = [];
        $xml = simplexml_load_string($sharedStringsXml);
        if ($xml === false || !isset($xml->si)) {
            return $sharedStrings;
        }

        foreach ($xml->si as $si) {
            $sharedStrings[] = $this->getXlsxNodeValue($si);
        }

        return $sharedStrings;
    }

    private function parseXlsxSheet(string $sheetXml, array $sharedStrings): array
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
                $index = $this->columnLetterToIndex($column);
                $value = isset($cell->v) ? (string) $cell->v : '';
                if ((string) $cell['t'] === 's') {
                    $id = intval($value);
                    $value = $sharedStrings[$id] ?? $value;
                }
                $cells[$index] = $value;
            }
            ksort($cells, SORT_NUMERIC);
            $rows[] = array_values($cells);
        }

        return $rows;
    }

    private function getXlsxNodeValue($node): string
    {
        if (isset($node->t)) {
            return (string) $node->t;
        }

        $value = '';
        foreach ($node->children() as $child) {
            $value .= $this->getXlsxNodeValue($child);
        }

        return $value;
    }

    private function columnLetterToIndex(string $column): int
    {
        $index = 0;
        $length = strlen($column);
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($column[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }
}
