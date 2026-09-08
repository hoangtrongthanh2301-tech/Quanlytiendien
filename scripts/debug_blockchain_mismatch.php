<?php
// Usage: php scripts/debug_blockchain_mismatch.php [--limit=N]
require __DIR__ . '/../config.php';
require __DIR__ . '/../blockchain.php';

$limit = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--limit=') === 0) {
        $limit = intval(substr($arg, 8));
    }
}

echo "Debug blockchain mismatches\n";
$rows = verifyBlockchainChain($conn);
if (!$rows['ok']) {
    echo "verifyBlockchainChain error: " . ($rows['error'] ?? 'unknown') . "\n";
    exit(1);
}
$count = 0;
foreach ($rows['rows'] as $r) {
    $count++;
    $bad = (!$r['valid_hash'] || !$r['valid_previous'] || !isset($r['valid_signature']) || !$r['valid_signature']);
    if ($bad) {
        echo "---- Block ID: {$r['id']} (maCSD={$r['maCSD']}) -----\n";
        echo "created_at: {$r['created_at']}\n";
        echo "stored previous_hash: " . ($r['previous_hash'] === null ? 'NULL' : $r['previous_hash']) . "\n";
        echo "stored current_hash : {$r['current_hash']}\n";
        // build raw fingerprint using helper
        $raw = buildBlockchainRecordFingerprint($r);
        echo "computed fingerprint: $raw\n";
        $expected = hashChainRecord($raw);
        echo "expected current_hash: $expected\n";
        echo "data_hash_matches: " . ($r['valid_hash'] ? 'YES' : 'NO') . "\n";
        $sigOk = isset($r['valid_signature']) && $r['valid_signature'] ? 'YES' : 'NO';
        echo "signature valid: $sigOk\n";
        echo "valid_previous: " . ($r['valid_previous'] ? 'YES' : 'NO') . "\n";
        // show previous block current_hash (if exists)
        $prevId = intval($r['id']) - 1;
        $pq = $conn->prepare('SELECT id, current_hash FROM blockchain_chisodien WHERE id < ? ORDER BY id DESC LIMIT 1');
        $pq->bind_param('i', $r['id']);
        $pq->execute();
        $pres = $pq->get_result()->fetch_assoc();
        if ($pres) {
            echo "prev block id: {$pres['id']} current_hash: {$pres['current_hash']}\n";
        } else {
            echo "prev block: (none)\n";
        }
        echo "----\n";
        if ($limit !== null && $limit <= 0) break;
        if ($limit !== null) $limit--;
    }
}

$conn->close();
echo "Done.\n";
