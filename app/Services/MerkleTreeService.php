<?php

namespace App\Services;

class MerkleTreeService
{
    public function buildMerkleRoot(array $hashes): string
    {
        if (empty($hashes)) {
            return '';
        }

        $layer = array_values($hashes);

        while (count($layer) > 1) {
            $layer = $this->buildNextLayer($layer);
        }

        return $layer[0];
    }

    private function buildNextLayer(array $layer): array
    {
        $nextLayer = [];
        $count = count($layer);

        for ($i = 0; $i < $count; $i += 2) {
            $left = $layer[$i];
            $right = $layer[$i + 1] ?? $left;
            $nextLayer[] = hash('sha256', $left . $right);
        }

        return $nextLayer;
    }
}
