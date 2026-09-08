<?php

namespace App\Models;

class BlockchainBatch
{
    public ?int $blockId;
    public int $thang;
    public int $nam;
    public int $batchIndex;
    public int $tongBanGhi;
    public string $merkleRoot;
    public ?string $previousHash;
    public string $currentHash;
    public string $signature;
    public string $signer;
    public string $createdAt;

    public function __construct(array $data)
    {
        $this->blockId = isset($data['block_id']) ? intval($data['block_id']) : null;
        $this->thang = intval($data['thang'] ?? 0);
        $this->nam = intval($data['nam'] ?? 0);
        $this->batchIndex = isset($data['batch_index']) ? intval($data['batch_index']) : 1;
        $this->tongBanGhi = intval($data['tong_ban_ghi'] ?? 0);
        $this->merkleRoot = $data['merkle_root'] ?? '';
        $this->previousHash = $data['previous_hash'] ?? null;
        $this->currentHash = $data['current_hash'] ?? '';
        $this->signature = $data['signature'] ?? '';
        $this->signer = $data['signer'] ?? 'system';
        $this->createdAt = $data['created_at'] ?? date('Y-m-d H:i:s');
    }

    public function toArray(): array
    {
        return [
            'block_id' => $this->blockId,
            'thang' => $this->thang,
            'nam' => $this->nam,
            'batch_index' => $this->batchIndex,
            'tong_ban_ghi' => $this->tongBanGhi,
            'merkle_root' => $this->merkleRoot,
            'previous_hash' => $this->previousHash,
            'current_hash' => $this->currentHash,
            'signature' => $this->signature,
            'signer' => $this->signer,
            'created_at' => $this->createdAt,
        ];
    }
}
