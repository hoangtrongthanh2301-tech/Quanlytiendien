<?php
header('Content-Type: application/json; charset=utf-8');
require 'config.php';

$sql = "SELECT g1.bac, g1.sanluong, g1.dongia, g1.ngayapdung
        FROM giadien g1
        INNER JOIN (
          SELECT bac, MAX(ngayapdung) AS max_date
          FROM giadien
          GROUP BY bac
        ) g2 ON g1.bac = g2.bac AND g1.ngayapdung = g2.max_date
        ORDER BY g1.bac ASC";

$result = mysqli_query($conn, $sql);
if (!$result) {
    echo json_encode(['success' => false, 'error' => 'Lỗi truy vấn: ' . mysqli_error($conn)]);
    exit;
}

$prices = [];
while ($row = mysqli_fetch_assoc($result)) {
    $prices[] = [
        'bac' => intval($row['bac']),
        'sanluong' => intval($row['sanluong']),
        'dongia' => floatval($row['dongia']),
        'ngayapdung' => $row['ngayapdung']
    ];
}

echo json_encode(['success' => true, 'prices' => $prices]);
