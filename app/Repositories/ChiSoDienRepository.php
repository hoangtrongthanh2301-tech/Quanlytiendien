<?php

namespace App\Repositories;

use App\Core\Database;
use App\Models\ChiSoDien;
use App\Services\HashService;

class ChiSoDienRepository
{
    private Database $database;
    private HashService $hashService;
    private array $tableColumns = [];

    public function __construct(Database $database, HashService $hashService)
    {
        $this->database = $database;
        $this->hashService = $hashService;
    }

    private function columnExists(string $column): bool
    {
        if (!array_key_exists($column, $this->tableColumns)) {
            $connection = $this->database->getConnection();
            $name = $connection->real_escape_string($column);
            $result = $connection->query("SHOW COLUMNS FROM `chisodien` LIKE '{$name}'");
            $this->tableColumns[$column] = $result && $result->num_rows > 0;
        }

        return $this->tableColumns[$column];
    }

    public function getByMonthYear(int $month, int $year): array
    {
        $connection = $this->database->getConnection();
        $selectColumns = ['maCSD', 'maKH', 'chisocu', 'chisomoi', 'thang', 'nam', 'ngaynhap'];
        if ($this->columnExists('created_at')) {
            $selectColumns[] = 'created_at';
        }
        if ($this->columnExists('updated_at')) {
            $selectColumns[] = 'updated_at';
        }
        if ($this->columnExists('batch_index')) {
            $selectColumns[] = 'batch_index';
        }

        $sql = sprintf('SELECT %s FROM chisodien WHERE thang = ? AND nam = ? ORDER BY maCSD ASC', implode(', ', $selectColumns));
        $stmt = $connection->prepare($sql);
        $stmt->bind_param('ii', $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = new ChiSoDien($row);
        }
        $stmt->close();

        return $rows;
    }

    public function getRecordHashesByMonthYear(int $month, int $year): array
    {
        $recordHashes = [];
        if ($this->columnExists('hash_record')) {
            $connection = $this->database->getConnection();
            $stmt = $connection->prepare('SELECT hash_record FROM chisodien WHERE thang = ? AND nam = ? ORDER BY maCSD ASC');
            $stmt->bind_param('ii', $month, $year);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                if (isset($row['hash_record'])) {
                    $recordHashes[] = $row['hash_record'];
                }
            }
            $stmt->close();
            return $recordHashes;
        }

        $records = $this->getByMonthYear($month, $year);
        foreach ($records as $record) {
            $recordHashes[] = $this->hashService->hashRecord($record->toArray());
        }

        return $recordHashes;
    }

    public function getRecordHashesByMonthYearAfterId(int $month, int $year, int $limit, ?int $afterId = null): array
    {
        return $this->getRecordHashPage($month, $year, $limit, $afterId)['hashes'];
    }

    public function getRecordHashPage(int $month, int $year, int $limit, ?int $afterId = null): array
    {
        if ($limit <= 0) {
            return ['hashes' => [], 'last_id' => null, 'count' => 0];
        }

        $connection = $this->database->getConnection();
        $selectColumns = 'maCSD, maKH, chisocu, chisomoi, thang, nam, ngaynhap';
        if ($this->columnExists('hash_record')) {
            $selectColumns .= ', hash_record';
        }

        $sql = sprintf('SELECT %s FROM chisodien WHERE thang = ? AND nam = ?' . ($afterId !== null ? ' AND maCSD > ?' : '') . ' ORDER BY maCSD ASC LIMIT ?', $selectColumns);
        $stmt = $connection->prepare($sql);
        if ($stmt === false) {
            return ['hashes' => [], 'last_id' => null, 'count' => 0];
        }

        if ($afterId !== null) {
            $stmt->bind_param('iiii', $month, $year, $afterId, $limit);
        } else {
            $stmt->bind_param('iii', $month, $year, $limit);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $hashes = [];
        $lastId = null;
        while ($row = $result->fetch_assoc()) {
            if (isset($row['hash_record']) && $row['hash_record'] !== '') {
                $hashes[] = $row['hash_record'];
            } else {
                $hashes[] = $this->hashService->hashRecord([
                    'maKH' => $row['maKH'],
                    'chisocu' => $row['chisocu'],
                    'chisomoi' => $row['chisomoi'],
                    'thang' => $row['thang'],
                    'nam' => $row['nam'],
                    'ngaynhap' => $row['ngaynhap'],
                ]);
            }
            $lastId = intval($row['maCSD']);
        }

        $stmt->close();

        return ['hashes' => $hashes, 'last_id' => $lastId, 'count' => count($hashes)];
    }

    public function setBatchIndexForRange(int $month, int $year, int $batchIndex, ?int $afterId, int $lastId): bool
    {
        if (!$this->columnExists('batch_index') || $lastId === null) {
            return true;
        }

        $connection = $this->database->getConnection();
        if ($afterId === null) {
            $stmt = $connection->prepare('UPDATE chisodien SET batch_index = ? WHERE thang = ? AND nam = ? AND maCSD <= ?');
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('iiii', $batchIndex, $month, $year, $lastId);
        } else {
            $stmt = $connection->prepare('UPDATE chisodien SET batch_index = ? WHERE thang = ? AND nam = ? AND maCSD > ? AND maCSD <= ?');
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('iiiii', $batchIndex, $month, $year, $afterId, $lastId);
        }

        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    public function setBatchIndexForMonthYear(int $month, int $year, int $batchIndex): bool
    {
        if (!$this->columnExists('batch_index')) {
            return true;
        }

        $connection = $this->database->getConnection();
        $stmt = $connection->prepare('UPDATE chisodien SET batch_index = ? WHERE thang = ? AND nam = ?');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('iii', $batchIndex, $month, $year);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    public function resetBatchIndexesForMonthYear(int $month, int $year): bool
    {
        if (!$this->columnExists('batch_index')) {
            return true;
        }

        $connection = $this->database->getConnection();
        $stmt = $connection->prepare('UPDATE chisodien SET batch_index = 0 WHERE thang = ? AND nam = ?');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ii', $month, $year);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    public function getExistingUniqueKeyMap(array $rows): array
    {
        $connection = $this->database->getConnection();
        $keys = [];
        foreach ($rows as $row) {
            if (!isset($row['maKH'], $row['thang'], $row['nam'])) {
                continue;
            }
            $maKH = $connection->real_escape_string($row['maKH']);
            $thang = intval($row['thang']);
            $nam = intval($row['nam']);
            $keys["{$maKH}-{$thang}-{$nam}"] = true;
        }

        if (empty($keys)) {
            return [];
        }

        $tuples = [];
        foreach (array_keys($keys) as $uniqueKey) {
            [$maKH, $thang, $nam] = explode('-', $uniqueKey);
            $tuples[] = sprintf("('%s', %d, %d)", $connection->real_escape_string($maKH), intval($thang), intval($nam));
        }

        $sql = 'SELECT maKH, thang, nam FROM chisodien WHERE (maKH, thang, nam) IN (' . implode(', ', $tuples) . ')';
        $result = $connection->query($sql);
        if (!$result) {
            return [];
        }

        $existing = [];
        while ($row = $result->fetch_assoc()) {
            $existing["{$row['maKH']}-{$row['thang']}-{$row['nam']}"] = true;
        }

        return $existing;
    }

    public function getByUniqueKeys(array $rows): array
    {
        $connection = $this->database->getConnection();
        $tuples = [];
        foreach ($rows as $row) {
            if (!isset($row['maKH'], $row['thang'], $row['nam'])) {
                continue;
            }
            $maKH = $connection->real_escape_string($row['maKH']);
            $thang = intval($row['thang']);
            $nam = intval($row['nam']);
            $tuples["{$maKH}-{$thang}-{$nam}"] = sprintf("('%s', %d, %d)", $maKH, $thang, $nam);
        }

        if (empty($tuples)) {
            return [];
        }

        $sql = 'SELECT maCSD, maKH, chisocu, chisomoi, thang, nam FROM chisodien WHERE (maKH, thang, nam) IN (' . implode(', ', $tuples) . ')';
        $result = $connection->query($sql);
        if (!$result) {
            return [];
        }

        $records = [];
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }

        return $records;
    }

    public function countByMonthYear(int $month, int $year): int
    {
        $connection = $this->database->getConnection();
        $stmt = $connection->prepare('SELECT COUNT(*) AS total FROM chisodien WHERE thang = ? AND nam = ?');
        $stmt->bind_param('ii', $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return intval($row['total'] ?? 0);
    }

    public function getSummaryByMonthYear(int $month, int $year): array
    {
        $connection = $this->database->getConnection();
        $stmt = $connection->prepare('SELECT COUNT(*) AS total_records, SUM(chisomoi - chisocu) AS total_consumption FROM chisodien WHERE thang = ? AND nam = ?');
        $stmt->bind_param('ii', $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $summary = $result->fetch_assoc() ?: [];
        $stmt->close();

        return [
            'total_records' => intval($summary['total_records'] ?? 0),
            'total_consumption' => intval($summary['total_consumption'] ?? 0),
        ];
    }

    public function saveBatch(array $rows): bool
    {
        $connection = $this->database->getConnection();
        if (empty($rows)) {
            return true;
        }

        $values = [];
        $includeHash = $this->columnExists('hash_record');
        foreach ($rows as $row) {
            $maKH = $connection->real_escape_string($row['maKH']);
            $chisocu = intval($row['chisocu']);
            $chisomoi = intval($row['chisomoi']);
            $thang = intval($row['thang']);
            $nam = intval($row['nam']);
            $ngaynhap = $connection->real_escape_string($row['ngaynhap']);
            $hashFragment = '';

            if ($includeHash) {
                $hashFragment = $connection->real_escape_string($this->hashService->hashRecord([
                    'maCSD' => $row['maCSD'] ?? 0,
                    'maKH' => $row['maKH'],
                    'chisocu' => $chisocu,
                    'chisomoi' => $chisomoi,
                    'thang' => $thang,
                    'nam' => $nam,
                    'ngaynhap' => $row['ngaynhap'],
                ]));
            }

            $values[] = sprintf("('%s', %d, %d, %d, %d, '%s'%s)",
                $maKH,
                $chisocu,
                $chisomoi,
                $thang,
                $nam,
                $ngaynhap,
                $includeHash ? ", '{$hashFragment}'" : ''
            );
        }

        $columns = 'maKH, chisocu, chisomoi, thang, nam, ngaynhap';
        if ($includeHash) {
            $columns .= ', hash_record';
        }

        $updateFragments = [
            'chisocu = VALUES(chisocu)',
            'chisomoi = VALUES(chisomoi)',
            'ngaynhap = VALUES(ngaynhap)',
        ];
        if ($this->columnExists('updated_at')) {
            $updateFragments[] = 'updated_at = CURRENT_TIMESTAMP';
        }
        if ($includeHash) {
            $updateFragments[] = 'hash_record = VALUES(hash_record)';
        }

        $sql = 'INSERT INTO chisodien (' . $columns . ') VALUES ' . implode(',', $values) .
            ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updateFragments);

        $this->database->beginTransaction();
        $result = $connection->query($sql);
        if ($result === false) {
            $this->database->rollback();
            return false;
        }

        return $this->database->commit();
    }

    public function getAllMonths(): array
    {
        $connection = $this->database->getConnection();
        $result = $connection->query('SELECT thang, nam, COUNT(*) AS total_records, SUM(chisomoi - chisocu) AS total_consumption FROM chisodien GROUP BY nam, thang ORDER BY nam DESC, thang DESC');
        if (!$result) {
            return [];
        }

        $months = [];
        while ($row = $result->fetch_assoc()) {
            $months[] = [
                'thang' => intval($row['thang']),
                'nam' => intval($row['nam']),
                'total_records' => intval($row['total_records']),
                'total_consumption' => intval($row['total_consumption']),
            ];
        }

        return $months;
    }
}
