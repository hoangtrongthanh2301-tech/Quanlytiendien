<?php

require_once __DIR__ . '/../config.php';

$queries = [
    "ALTER TABLE chisodien ADD COLUMN IF NOT EXISTS hash_record CHAR(64) NOT NULL DEFAULT '' AFTER chisomoi",
    "ALTER TABLE chisodien ADD COLUMN IF NOT EXISTS batch_index INT(11) NOT NULL DEFAULT 0 AFTER hash_record",
    "ALTER TABLE chisodien ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER ngaynhap",
    "ALTER TABLE chisodien ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
    "ALTER TABLE chisodien ADD KEY IF NOT EXISTS idx_thang_nam (thang, nam)",
    "ALTER TABLE chisodien ADD KEY IF NOT EXISTS idx_chisodien_ngaynhap (ngaynhap)",
    "ALTER TABLE chisodien ADD KEY IF NOT EXISTS idx_chisodien_makh_ngaynhap (maKH, ngaynhap)",
    "ALTER TABLE blockchain_batch ADD COLUMN IF NOT EXISTS batch_index INT(11) NOT NULL DEFAULT 1 AFTER nam",
    "ALTER TABLE blockchain_batch DROP INDEX IF EXISTS uniq_block_month",
    "ALTER TABLE blockchain_batch ADD UNIQUE KEY uniq_block_month (thang, nam, batch_index)",
    "ALTER TABLE blockchain_batch ADD KEY IF NOT EXISTS idx_month_year (thang, nam)",
];

foreach ($queries as $query) {
    if ($conn->query($query) === false) {
        echo "FAILED: {$query}\n";
        echo "ERROR: " . $conn->error . "\n";
        exit(1);
    }
    echo "OK: {$query}\n";
}

echo "Schema migration complete.\n";
