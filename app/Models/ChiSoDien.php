<?php

namespace App\Models;

class ChiSoDien
{
    public ?int $maCSD;
    public string $maKH;
    public int $chisocu;
    public int $chisomoi;
    public string $hashRecord;
    public int $batchIndex;
    public int $thang;
    public int $nam;
    public string $ngaynhap;
    public ?string $createdAt;
    public ?string $updatedAt;

    public function __construct(array $data)
    {
        $this->maCSD = isset($data['maCSD']) ? intval($data['maCSD']) : null;
        $this->maKH = $data['maKH'] ?? '';
        $this->chisocu = intval($data['chisocu'] ?? 0);
        $this->chisomoi = intval($data['chisomoi'] ?? 0);
        $this->hashRecord = $data['hash_record'] ?? '';
        $this->batchIndex = isset($data['batch_index']) ? intval($data['batch_index']) : 0;
        $this->thang = intval($data['thang'] ?? 0);
        $this->nam = intval($data['nam'] ?? 0);
        $this->ngaynhap = $data['ngaynhap'] ?? date('Y-m-d');
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'maCSD' => $this->maCSD,
            'maKH' => $this->maKH,
            'chisocu' => $this->chisocu,
            'chisomoi' => $this->chisomoi,
            'hash_record' => $this->hashRecord,
            'batch_index' => $this->batchIndex,
            'thang' => $this->thang,
            'nam' => $this->nam,
            'ngaynhap' => $this->ngaynhap,
        ];
    }
}
