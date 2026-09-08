<?php

function getBlockChainPrivateKeyPath() {
    if (defined('BLOCKCHAIN_PRIVATE_KEY_FILE') && BLOCKCHAIN_PRIVATE_KEY_FILE) {
        return BLOCKCHAIN_PRIVATE_KEY_FILE;
    }
    return __DIR__ . '/storage/keys/blockchain_private.pem';
}

function getBlockChainPublicKeyPath() {
    if (defined('BLOCKCHAIN_PUBLIC_KEY_FILE') && BLOCKCHAIN_PUBLIC_KEY_FILE) {
        return BLOCKCHAIN_PUBLIC_KEY_FILE;
    }
    return __DIR__ . '/storage/keys/blockchain_public.pem';
}

function loadPrivateKey() {
    $privateKeyFile = getBlockChainPrivateKeyPath();
    if (!file_exists($privateKeyFile)) {
        return false;
    }
    $key = file_get_contents($privateKeyFile);
    if ($key === false) {
        return false;
    }
    $res = openssl_pkey_get_private($key);
    return $res ?: false;
}

function loadPublicKey() {
    $publicKeyFile = getBlockChainPublicKeyPath();
    if (!file_exists($publicKeyFile)) {
        return false;
    }
    $key = file_get_contents($publicKeyFile);
    if ($key === false) {
        return false;
    }
    $res = openssl_pkey_get_public($key);
    return $res ?: false;
}

function hashChainRecord($data) {
    return hash('sha256', $data);
}

function signHash($hash) {
    $privateKey = loadPrivateKey();
    if ($privateKey === false) {
        return false;
    }
    $signature = '';
    if (!openssl_sign($hash, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        return false;
    }
    openssl_pkey_free($privateKey);
    return base64_encode($signature);
}

function verifySignature($hash, $signature) {
    $publicKey = loadPublicKey();
    if ($publicKey === false) {
        return false;
    }
    $decoded = base64_decode($signature, true);
    if ($decoded === false) {
        openssl_pkey_free($publicKey);
        return false;
    }
    $result = openssl_verify($hash, $decoded, $publicKey, OPENSSL_ALGO_SHA256);
    openssl_pkey_free($publicKey);
    return $result === 1;
}

function normalizePreviousHash($hash) {
    if ($hash === null || $hash === '') {
        return null;
    }
    return trim($hash);
}

function buildBlockchainRecordFingerprint(array $record) {
    return sprintf(
        '%s|%s|%d|%d|%d|%d',
        $record['maCSD'],
        $record['maKH'],
        intval($record['chisocu']),
        intval($record['chisomoi']),
        intval($record['thang']),
        intval($record['nam'])
    );
}

function getLastBlockHash($conn) {
    $result = $conn->query("SELECT current_hash FROM blockchain_chisodien ORDER BY id DESC LIMIT 1");
    if (!$result) {
        return null;
    }
    $row = $result->fetch_assoc();
    return $row ? $row['current_hash'] : null;
}

function createBlockchainRecordRaw($conn, $maCSD, $maKH, $chisocu, $chisomoi, $thang, $nam, $createdAt = null, $signer = 'admin') {
    $previousHash = getLastBlockHash($conn);
    if ($createdAt === null) {
        $createdAt = date('Y-m-d H:i:s');
    }
    $record = [
        'maCSD' => $maCSD,
        'maKH' => $maKH,
        'chisocu' => $chisocu,
        'chisomoi' => $chisomoi,
        'thang' => $thang,
        'nam' => $nam,
        'created_at' => $createdAt,
        'previous_hash' => $previousHash
    ];
    $raw = buildBlockchainRecordFingerprint($record);
    $currentHash = hashChainRecord($raw . '|' . ($previousHash ?? ''));
    $signature = signHash($currentHash);
    if ($signature === false) {
        return false;
    }

    $stmt = $conn->prepare(
        'INSERT INTO blockchain_chisodien (maCSD, maKH, chisocu, chisomoi, thang, nam, created_at, previous_hash, current_hash, signature, signer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ssiiiisssss', $maCSD, $maKH, $chisocu, $chisomoi, $thang, $nam, $createdAt, $previousHash, $currentHash, $signature, $signer);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function createBlockchainRecord($conn, $maCSD, $maKH, $chisocu, $chisomoi, $thang, $nam, $signer = 'admin') {
    return createBlockchainRecordRaw($conn, $maCSD, $maKH, $chisocu, $chisomoi, $thang, $nam, null, $signer);
}

function verifyBlockchainChain($conn) {
    $rows = [];
    $result = $conn->query("SELECT id, maCSD, maKH, chisocu, chisomoi, thang, nam, created_at, previous_hash, current_hash, signature FROM blockchain_chisodien ORDER BY id ASC");
    if (!$result) {
        return ['ok' => false, 'error' => $conn->error];
    }
    $prevHash = null;
    while ($row = $result->fetch_assoc()) {
        $raw = buildBlockchainRecordFingerprint($row);
        $expectedHash = hashChainRecord($raw . '|' . ($prevHash ?? ''));
        $validHash = $expectedHash === $row['current_hash'];
        $rowPrev = normalizePreviousHash($row['previous_hash']);
        $validPrev = ($prevHash === $rowPrev);
        $validSignature = verifySignature($expectedHash, $row['signature']);
        $rows[] = array_merge($row, ['valid_hash' => $validHash, 'valid_previous' => $validPrev]);
        $rows[count($rows) - 1]['valid_signature'] = $validSignature;
        $prevHash = $row['current_hash'];
    }
    return ['ok' => true, 'rows' => $rows];
}

function buildMerkleRootFromHashes(array $hashes): string {
    if (empty($hashes)) {
        return '';
    }

    $layer = array_values($hashes);
    while (count($layer) > 1) {
        $nextLayer = [];
        $count = count($layer);
        for ($i = 0; $i < $count; $i += 2) {
            $left = $layer[$i];
            $right = $layer[$i + 1] ?? $left;
            $nextLayer[] = hash('sha256', $left . $right);
        }
        $layer = $nextLayer;
    }

    return $layer[0];
}

function buildRecordHash(array $record): string {
    return hash('sha256', sprintf(
        '%s|%d|%d|%d|%d|%s',
        $record['maKH'],
        intval($record['chisocu']),
        intval($record['chisomoi']),
        intval($record['thang']),
        intval($record['nam']),
        $record['ngaynhap'] ?? ''
    ));
}

function getRecordHashesForMonthYear($conn, int $month, int $year): array {
    $hashes = [];
    $stmt = $conn->prepare('SELECT maKH, chisocu, chisomoi, thang, nam, ngaynhap FROM chisodien WHERE thang = ? AND nam = ? ORDER BY maCSD ASC');
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ii', $month, $year);
    $stmt->execute();
    $stmt->bind_result($maKH, $chisocu, $chisomoi, $thang, $nam, $ngaynhap);
    while ($stmt->fetch()) {
        $hashes[] = buildRecordHash([
            'maKH' => $maKH,
            'chisocu' => $chisocu,
            'chisomoi' => $chisomoi,
            'thang' => $thang,
            'nam' => $nam,
            'ngaynhap' => $ngaynhap,
        ]);
    }
    $stmt->close();

    return $hashes;
}

function getLastBatchHash($conn) {
    $result = $conn->query('SELECT current_hash FROM blockchain_batch ORDER BY block_id DESC LIMIT 1');
    if (!$result) {
        return null;
    }
    $row = $result->fetch_assoc();
    return $row ? $row['current_hash'] : null;
}

function createBlockchainBatchRaw($conn, int $month, int $year, int $recordCount, string $merkleRoot, string $createdAt = null, string $signer = 'system') {
    $previousHash = getLastBatchHash($conn);
    if ($createdAt === null) {
        $createdAt = date('Y-m-d H:i:s');
    }
    $currentHash = hashChainRecord(($previousHash ?? '') . $merkleRoot . $createdAt);
    $signature = signHash($currentHash);
    if ($signature === false) {
        return false;
    }

    $stmt = $conn->prepare(
        'INSERT INTO blockchain_batch (thang, nam, batch_index, tong_ban_ghi, merkle_root, previous_hash, current_hash, signature, signer, created_at) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE tong_ban_ghi = VALUES(tong_ban_ghi), merkle_root = VALUES(merkle_root), previous_hash = VALUES(previous_hash), current_hash = VALUES(current_hash), signature = VALUES(signature), signer = VALUES(signer), created_at = VALUES(created_at)'
    );
    if (!$stmt) {
        return false;
    }
    $batchIndex = 1;
    $stmt->bind_param('iiiissssss', $month, $year, $batchIndex, $recordCount, $merkleRoot, $previousHash, $currentHash, $signature, $signer, $createdAt);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function createBlockchainBatch($conn, int $month, int $year, int $recordCount, string $merkleRoot, string $signer = 'system') {
    return createBlockchainBatchRaw($conn, $month, $year, $recordCount, $merkleRoot, null, $signer);
}

function verifyBlockchainBatchForMonthYear($conn, int $month, int $year): array {
    $stmt = $conn->prepare('SELECT block_id, thang, nam, batch_index, tong_ban_ghi, merkle_root, previous_hash, current_hash, signature, signer, created_at FROM blockchain_batch WHERE thang = ? AND nam = ? LIMIT 1');
    if (!$stmt) {
        return ['valid' => false, 'error' => $conn->error];
    }
    $stmt->bind_param('ii', $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $batch = $result->fetch_assoc();
    $stmt->close();

    if (!$batch) {
        return ['valid' => false, 'error' => 'Không tìm thấy batch blockchain cho tháng/năm được chọn.'];
    }

    $recordHashes = getRecordHashesForMonthYear($conn, $month, $year);
    $computedMerkleRoot = buildMerkleRootFromHashes($recordHashes);
    $expectedCurrentHash = hashChainRecord(($batch['previous_hash'] ?? '') . $computedMerkleRoot . $batch['created_at']);
    $signatureValid = verifySignature($expectedCurrentHash, $batch['signature']);

    return [
        'valid' => $computedMerkleRoot === $batch['merkle_root'] && $expectedCurrentHash === $batch['current_hash'] && $signatureValid,
        'computed_merkle_root' => $computedMerkleRoot,
        'stored_merkle_root' => $batch['merkle_root'],
        'computed_current_hash' => $expectedCurrentHash,
        'stored_current_hash' => $batch['current_hash'],
        'signature_valid' => $signatureValid,
        'record_count' => count($recordHashes),
        'batch' => $batch,
    ];
}

function verifyBlockchainBatchChain($conn): array {
    $issues = [];
    $previousHash = null;
    $result = $conn->query('SELECT block_id, thang, nam, batch_index, tong_ban_ghi, merkle_root, previous_hash, current_hash, signature, signer, created_at FROM blockchain_batch ORDER BY block_id ASC');
    if (!$result) {
        return ['ok' => false, 'error' => $conn->error];
    }

    while ($row = $result->fetch_assoc()) {
        $expectedCurrentHash = hashChainRecord(($previousHash ?? '') . $row['merkle_root'] . $row['created_at']);
        $signatureValid = verifySignature($expectedCurrentHash, $row['signature']);
        $previousValid = normalizePreviousHash($row['previous_hash']) === $previousHash;
        $currentValid = $expectedCurrentHash === $row['current_hash'];

        if (!$currentValid || !$signatureValid || !$previousValid) {
            $issues[] = [
                'block_id' => $row['block_id'],
                'month' => $row['thang'],
                'year' => $row['nam'],
                'current_valid' => $currentValid,
                'previous_valid' => $previousValid,
                'signature_valid' => $signatureValid,
                'expected_current_hash' => $expectedCurrentHash,
                'stored_current_hash' => $row['current_hash'],
                'stored_previous_hash' => $row['previous_hash'],
                'expected_previous_hash' => $previousHash,
            ];
        }

        $previousHash = $row['current_hash'];
    }

    return ['ok' => empty($issues), 'issues' => $issues];
}
