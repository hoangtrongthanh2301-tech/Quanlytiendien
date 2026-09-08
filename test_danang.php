<?php
require 'config.php';

$query = "SELECT DISTINCT diachi FROM taikhoan WHERE diachi LIKE '%Nang%' LIMIT 3";
$result = $conn->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "Address: " . $row['diachi'] . "\n";
    }
} else {
    echo "Error: " . $conn->error;
}

// Test the province extraction
require 'admin-stats.php';
echo "\nTesting province extraction:\n";
$testAddresses = [
    "56 Nguyễn Trãi, Hải Châu, Đà Nẵng",
    "22 Trần Phú, Đà Nẵng",
    "11 Võ Văn Tần, Da Nang"
];

foreach ($testAddresses as $addr) {
    $prov = extractProvinceFromAddress($addr);
    $norm = normalizeProvinceName($prov);
    echo "Address: $addr\n";
    echo "  Province: $prov\n";
    echo "  Normalized: $norm\n";
}
?>
