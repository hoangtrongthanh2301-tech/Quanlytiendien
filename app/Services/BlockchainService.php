<?php

namespace App\Services;

use App\Models\BlockchainBatch;
use App\Repositories\BlockchainBatchRepository;
use App\Repositories\ChiSoDienRepository;

class BlockchainService
{
    private BlockchainBatchRepository $batchRepository;
    private ChiSoDienRepository $chisodienRepository;
    private HashService $hashService;
    private MerkleTreeService $merkleTreeService;

    public function __construct(
        BlockchainBatchRepository $batchRepository,
        ChiSoDienRepository $chisodienRepository,
        HashService $hashService,
        MerkleTreeService $merkleTreeService
    ) {
        $this->batchRepository = $batchRepository;
        $this->chisodienRepository = $chisodienRepository;
        $this->hashService = $hashService;
        $this->merkleTreeService = $merkleTreeService;
    }

    public function createMonthlyBatch(int $month, int $year, string $signer = 'system'): BlockchainBatch
    {
        $recordHashes = $this->chisodienRepository->getRecordHashesByMonthYear($month, $year);
        if (empty($recordHashes)) {
            throw new \RuntimeException('Không có dữ liệu chỉ số cho tháng/năm được chọn.');
        }

        $existingBatches = $this->batchRepository->getByMonthYear($month, $year);
        if (!empty($existingBatches)) {
            $futureBatches = $this->batchRepository->getBatchesAfterMonthYear($month, $year);
            if (!empty($futureBatches)) {
                throw new \RuntimeException('Không thể tạo batch lịch sử khi tồn tại batch về sau. Vui lòng kiểm tra và tái tạo chuỗi sau.');
            }
            $this->batchRepository->deleteByMonthYear($month, $year);
            $this->chisodienRepository->resetBatchIndexesForMonthYear($month, $year);
        }

        $merkleRoot = $this->merkleTreeService->buildMerkleRoot($recordHashes);
        $previousBatch = $this->batchRepository->getLastBatchBeforeMonthYear($month, $year);
        $previousHash = $previousBatch ? $previousBatch->currentHash : null;
        $createdAt = date('Y-m-d H:i:s');
        $currentHash = $this->calculateCurrentHash($previousHash, $merkleRoot, $createdAt);
        $signature = $this->signBatchHash($currentHash);

        $batch = new BlockchainBatch([
            'thang' => $month,
            'nam' => $year,
            'batch_index' => 1,
            'tong_ban_ghi' => count($recordHashes),
            'merkle_root' => $merkleRoot,
            'previous_hash' => $previousHash,
            'current_hash' => $currentHash,
            'signature' => $signature,
            'signer' => $signer,
            'created_at' => $createdAt,
        ]);

        if (!$this->batchRepository->save($batch)) {
            throw new \RuntimeException('Lưu batch blockchain thất bại.');
        }

        if (!$this->chisodienRepository->setBatchIndexForMonthYear($month, $year, 1)) {
            throw new \RuntimeException('Không thể gán batch index cho bản ghi chỉ số.');
        }

        return $batch;
    }

    public function createMonthlyBatches(int $month, int $year, int $batchSize = 1000, string $signer = 'system'): array
    {
        if ($batchSize < 1) {
            throw new \InvalidArgumentException('Kích thước batch phải lớn hơn 0.');
        }

        $totalRecords = $this->chisodienRepository->countByMonthYear($month, $year);
        if ($totalRecords === 0) {
            throw new \RuntimeException('Không có dữ liệu chỉ số cho tháng/năm được chọn.');
        }

        $existingBatches = $this->batchRepository->getByMonthYear($month, $year);
        if (!empty($existingBatches)) {
            $futureBatches = $this->batchRepository->getBatchesAfterMonthYear($month, $year);
            if (!empty($futureBatches)) {
                throw new \RuntimeException('Không thể xây dựng lại batch cho tháng/năm này khi đã tồn tại batch về sau. Vui lòng xác thực và tái tạo chuỗi sau.');
            }
            $this->batchRepository->deleteByMonthYear($month, $year);
            $this->chisodienRepository->resetBatchIndexesForMonthYear($month, $year);
        }

        $batches = [];
        $previousHash = $this->batchRepository->getLastBatchBeforeMonthYear($month, $year)?->currentHash;
        $afterId = null;
        $batchIndex = 1;
        $remaining = $totalRecords;

        while ($remaining > 0) {
            $page = $this->chisodienRepository->getRecordHashPage($month, $year, min($batchSize, $remaining), $afterId);
            if (empty($page['hashes'])) {
                break;
            }

            $merkleRoot = $this->merkleTreeService->buildMerkleRoot($page['hashes']);
            $createdAt = date('Y-m-d H:i:s');
            $currentHash = $this->calculateCurrentHash($previousHash, $merkleRoot, $createdAt);
            $signature = $this->signBatchHash($currentHash);

            $batch = new BlockchainBatch([
                'thang' => $month,
                'nam' => $year,
                'batch_index' => $batchIndex,
                'tong_ban_ghi' => $page['count'],
                'merkle_root' => $merkleRoot,
                'previous_hash' => $previousHash,
                'current_hash' => $currentHash,
                'signature' => $signature,
                'signer' => $signer,
                'created_at' => $createdAt,
            ]);

            if (!$this->batchRepository->save($batch)) {
                throw new \RuntimeException(sprintf('Lưu batch blockchain thất bại cho batch_index %d.', $batchIndex));
            }

            if (!$this->chisodienRepository->setBatchIndexForRange($month, $year, $batchIndex, $afterId, $page['last_id'])) {
                throw new \RuntimeException(sprintf('Không thể gán batch index cho bản ghi tháng %d/năm %d batch %d.', $month, $year, $batchIndex));
            }

            $batches[] = $batch;
            $previousHash = $currentHash;
            $afterId = $page['last_id'];
            $remaining -= $page['count'];
            $batchIndex++;
        }

        if (empty($batches)) {
            throw new \RuntimeException('Không tạo được batch blockchain.');
        }

        return $batches;
    }

    public function calculateCurrentHash(?string $previousHash, string $merkleRoot, string $timestamp): string
    {
        $previousHash = $previousHash ?? '';
        return $this->hashService->hashString($previousHash . $merkleRoot . $timestamp);
    }

    public function signBatchHash(string $hash): string
    {
        $privateKeyPath = defined('BLOCKCHAIN_PRIVATE_KEY_FILE') ? BLOCKCHAIN_PRIVATE_KEY_FILE : __DIR__ . '/../../blockchain_private.pem';
        if (!file_exists($privateKeyPath)) {
            throw new \RuntimeException('Khóa riêng tư blockchain không tồn tại.');
        }

        $keyContents = file_get_contents($privateKeyPath);
        if ($keyContents === false) {
            throw new \RuntimeException('Không đọc được khóa riêng tư blockchain.');
        }

        $privateKey = openssl_pkey_get_private($keyContents);
        if ($privateKey === false) {
            throw new \RuntimeException('Khóa riêng tư blockchain không hợp lệ.');
        }

        $signature = '';
        if (!openssl_sign($hash, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            openssl_pkey_free($privateKey);
            throw new \RuntimeException('Ký hash blockchain thất bại.');
        }

        openssl_pkey_free($privateKey);

        return base64_encode($signature);
    }
}
