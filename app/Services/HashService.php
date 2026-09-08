<?php

namespace App\Services;

class HashService
{
    public function hashRecord(array $record): string
    {
        $canonical = sprintf(
            '%s|%d|%d|%d|%d|%s',
            $record['maKH'] ?? '',
            intval($record['chisocu'] ?? 0),
            intval($record['chisomoi'] ?? 0),
            intval($record['thang'] ?? 0),
            intval($record['nam'] ?? 0),
            $record['ngaynhap'] ?? ''
        );

        return hash('sha256', $canonical);
    }

    public function hashString(string $value): string
    {
        return hash('sha256', $value);
    }
}
