<?php
require_once dirname(__DIR__) . '/config.php';

echo "Scanning for duplicate invoices by maCSD...\n";

$sql = "SELECT maCSD, COUNT(*) AS cnt, GROUP_CONCAT(maHD ORDER BY ngaytao DESC SEPARATOR ',') AS ids
        FROM hoadon
        GROUP BY maCSD
        HAVING cnt > 1";

$res = $conn->query($sql);
if (!$res) {
    echo "Query failed: " . $conn->error . "\n";
    exit(1);
}

$rows = $res->fetch_all(MYSQLI_ASSOC);
if (count($rows) === 0) {
    echo "No duplicate invoices found.\n";
    exit(0);
}

foreach ($rows as $r) {
    $maCSD = $r['maCSD'];
    $cnt = $r['cnt'];
    $ids = $r['ids'];
    $idList = array_map('trim', explode(',', $ids));
    // Keep the first id in the ordered list (newest by ngaytao), remove others
    $keep = array_shift($idList);
    $deleteList = implode(',', array_map('intval', $idList));

    echo "maCSD={$maCSD} has {$cnt} invoices. Keep maHD={$keep}.\n";
    echo "  maHDs: {$ids}\n";
    if (!empty($deleteList)) {
        echo "  Suggested cleanup SQL:\n";
        echo "    DELETE FROM hoadon WHERE maHD IN ({$deleteList});\n";
        echo "    -- and optionally: DELETE FROM thanhtoan WHERE maHD IN ({$deleteList});\n";
    }
    echo "\n";
}

$conn->close();

echo "Done. Review suggested SQL before running.\n";

?>
