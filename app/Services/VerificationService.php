<?php

namespace App\Services;

use App\Repositories\BlockchainBatchRepository;
use App\Repositories\ChiSoDienRepository;

class VerificationService
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

    public function verifyBatch(int $month, int $year): array
    {
        $batches = $this->batchRepository->getByMonthYear($month, $year);
        if (empty($batches)) {
            return ['valid' => false, 'error' => 'Không tồn tại batch blockchain cho tháng/năm đã chọn.'];
        }

        $previousHash = $this->batchRepository->getLastBatchBeforeMonthYear($month, $year)?->currentHash;
        $afterId = null;
        $recordCount = 0;
        $issues = [];
        $batchResults = [];

        foreach ($batches as $batch) {
            $page = $this->chisodienRepository->getRecordHashPage($month, $year, $batch->tongBanGhi, $afterId);
            $computedMerkleRoot = $this->merkleTreeService->buildMerkleRoot($page['hashes']);
            $computedCurrentHash = $this->hashService->hashString(($previousHash ?? '') . $computedMerkleRoot . $batch->createdAt);
            $signatureValid = $this->verifySignature($computedCurrentHash, $batch->signature);
            $matchingPrevious = $batch->previousHash === $previousHash;
            $validMerkle = $computedMerkleRoot === $batch->merkleRoot;
            $validHash = $computedCurrentHash === $batch->currentHash;
            $validCount = $page['count'] === $batch->tongBanGhi;

            $batchResults[] = [
                'batch_index' => $batch->batchIndex,
                'valid_merkle_root' => $validMerkle,
                'valid_current_hash' => $validHash,
                'valid_previous_hash' => $matchingPrevious,
                'signature_valid' => $signatureValid,
                'record_count' => $page['count'],
                'expected_record_count' => $batch->tongBanGhi,
                'computed_merkle_root' => $computedMerkleRoot,
                'expected_merkle_root' => $batch->merkleRoot,
                'computed_current_hash' => $computedCurrentHash,
                'expected_current_hash' => $batch->currentHash,
                'previous_hash' => $batch->previousHash,
                'expected_previous_hash' => $previousHash,
            ];

            if (!$validMerkle || !$validHash || !$signatureValid || !$matchingPrevious || !$validCount) {
                $issues[] = [
                    'batch_index' => $batch->batchIndex,
                    'valid_merkle_root' => $validMerkle,
                    'valid_current_hash' => $validHash,
                    'valid_previous_hash' => $matchingPrevious,
                    'signature_valid' => $signatureValid,
                    'record_count' => $page['count'],
                    'expected_record_count' => $batch->tongBanGhi,
                ];
            }

            $previousHash = $batch->currentHash;
            $afterId = $page['last_id'];
            $recordCount += $page['count'];
        }

        $remainingPage = $this->chisodienRepository->getRecordHashPage($month, $year, 1, $afterId);
        if (!empty($remainingPage['hashes'])) {
            $issues[] = [
                'batch_index' => null,
                'error' => 'Còn bản ghi chỉ số chưa được bao phủ bởi batch.',
                'remaining_records' => $remainingPage['count'],
            ];
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'batch_results' => $batchResults,
            'record_count' => $recordCount,
            'batches' => array_map(fn($batch) => $batch->toArray(), $batches),
        ];
    }

    public function verifyChain(): array
    {
        $batches = $this->batchRepository->getAllBatches();
        $issues = [];
        $previousHash = null;

        foreach ($batches as $batch) {
            $expectedCurrentHash = $this->hashService->hashString(($previousHash ?? '') . $batch->merkleRoot . $batch->createdAt);
            $signatureValid = $this->verifySignature($expectedCurrentHash, $batch->signature);
            $validHash = $expectedCurrentHash === $batch->currentHash;

            if (!$validHash || !$signatureValid || $batch->previousHash !== $previousHash) {
                $issues[] = [
                    'block_id' => $batch->blockId,
                    'month' => $batch->thang,
                    'year' => $batch->nam,
                    'valid_hash' => $validHash,
                    'previous_match' => $batch->previousHash === $previousHash,
                    'signature_valid' => $signatureValid,
                ];
            }

            $previousHash = $batch->currentHash;
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'total_batches' => count($batches),
        ];
    }

    private function verifySignature(string $hash, string $signature): bool
    {
        $publicKeyPath = defined('BLOCKCHAIN_PUBLIC_KEY_FILE') ? BLOCKCHAIN_PUBLIC_KEY_FILE : __DIR__ . '/../../blockchain_public.pem';
        if (!file_exists($publicKeyPath)) {
            return false;
        }

        $keyContents = file_get_contents($publicKeyPath);
        if ($keyContents === false) {
            return false;
        }

        $publicKey = openssl_pkey_get_public($keyContents);
        if ($publicKey === false) {
            return false;
        }

        $decoded = base64_decode($signature, true);
        if ($decoded === false) {
            openssl_pkey_free($publicKey);
            return false;
        }

        $verified = openssl_verify($hash, $decoded, $publicKey, OPENSSL_ALGO_SHA256) === 1;
        openssl_pkey_free($publicKey);

        return $verified;
    }
}
