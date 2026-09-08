<?php

namespace App\Repositories;

use App\Core\Database;
use App\Models\BlockchainBatch;

class BlockchainBatchRepository
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function getLastBatch(): ?BlockchainBatch
    {
        $connection = $this->database->getConnection();
        $result = $connection->query('SELECT block_id, thang, nam, batch_index, tong_ban_ghi, merkle_root, previous_hash, current_hash, signature, signer, created_at FROM blockchain_batch ORDER BY nam DESC, thang DESC, batch_index DESC, block_id DESC LIMIT 1');
        if (!$result) {
            return null;
        }

        $row = $result->fetch_assoc();
        return $row ? new BlockchainBatch($row) : null;
    }

    public function getByMonthYear(int $month, int $year): array
    {
        $connection = $this->database->getConnection();
        $stmt = $connection->prepare('SELECT block_id, thang, nam, batch_index, tong_ban_ghi, merkle_root, previous_hash, current_hash, signature, signer, created_at FROM blockchain_batch WHERE thang = ? AND nam = ? ORDER BY batch_index ASC');
        $stmt->bind_param('ii', $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = new BlockchainBatch($row);
        }
        $stmt->close();

        return $rows;
    }

    public function getLastBatchBeforeMonthYear(int $month, int $year): ?BlockchainBatch
    {
        $connection = $this->database->getConnection();
        $stmt = $connection->prepare(
            'SELECT block_id, thang, nam, batch_index, tong_ban_ghi, merkle_root, previous_hash, current_hash, signature, signer, created_at '
            . 'FROM blockchain_batch WHERE (nam < ?) OR (nam = ? AND thang < ?) '
            . 'ORDER BY nam DESC, thang DESC, batch_index DESC LIMIT 1'
        );
        $stmt->bind_param('iii', $year, $year, $month);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row ? new BlockchainBatch($row) : null;
    }

    public function getBatchesAfterMonthYear(int $month, int $year): array
    {
        $connection = $this->database->getConnection();
        $stmt = $connection->prepare(
            'SELECT block_id, thang, nam, batch_index, tong_ban_ghi, merkle_root, previous_hash, current_hash, signature, signer, created_at '
            . 'FROM blockchain_batch WHERE (nam > ?) OR (nam = ? AND thang > ?) '
            . 'ORDER BY nam ASC, thang ASC, batch_index ASC'
        );
        $stmt->bind_param('iii', $year, $year, $month);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = new BlockchainBatch($row);
        }
        $stmt->close();

        return $rows;
    }

    public function deleteByMonthYear(int $month, int $year): bool
    {
        $connection = $this->database->getConnection();
        $stmt = $connection->prepare('DELETE FROM blockchain_batch WHERE thang = ? AND nam = ?');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ii', $month, $year);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    public function updateBatchHashes(BlockchainBatch $batch): bool
    {
        $connection = $this->database->getConnection();
        $stmt = $connection->prepare('UPDATE blockchain_batch SET previous_hash = ?, current_hash = ?, signature = ? WHERE block_id = ?');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('sssi', $batch->previousHash, $batch->currentHash, $batch->signature, $batch->blockId);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    public function save(BlockchainBatch $batch): bool
    {
        $connection = $this->database->getConnection();
        $stmt = $connection->prepare(
            'INSERT INTO blockchain_batch (thang, nam, batch_index, tong_ban_ghi, merkle_root, previous_hash, current_hash, signature, signer, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)' .
            ' ON DUPLICATE KEY UPDATE tong_ban_ghi = VALUES(tong_ban_ghi), merkle_root = VALUES(merkle_root), previous_hash = VALUES(previous_hash), current_hash = VALUES(current_hash), signature = VALUES(signature), signer = VALUES(signer), created_at = VALUES(created_at)'
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            'iiiissssss',
            $batch->thang,
            $batch->nam,
            $batch->batchIndex,
            $batch->tongBanGhi,
            $batch->merkleRoot,
            $batch->previousHash,
            $batch->currentHash,
            $batch->signature,
            $batch->signer,
            $batch->createdAt
        );

        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    public function getAllBatches(): array
    {
        $connection = $this->database->getConnection();
        $result = $connection->query('SELECT block_id, thang, nam, batch_index, tong_ban_ghi, merkle_root, previous_hash, current_hash, signature, signer, created_at FROM blockchain_batch ORDER BY nam ASC, thang ASC, batch_index ASC, block_id ASC');
        if (!$result) {
            return [];
        }

        $batches = [];
        while ($row = $result->fetch_assoc()) {
            $batches[] = new BlockchainBatch($row);
        }

        return $batches;
    }
}
