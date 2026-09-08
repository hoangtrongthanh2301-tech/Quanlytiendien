<?php
require_once dirname(__DIR__) . '/config.php';

$sql1 = "SELECT COUNT(*) AS cnt FROM hoadon h JOIN chisodien c ON h.maCSD=c.maCSD WHERE (c.nam<2026 OR (c.nam=2026 AND c.thang<5)) AND h.trangthai='chuathanhtoan'";
$res = $conn->query($sql1);
$row = $res->fetch_assoc();
echo 'invoices_unpaid=' . intval($row['cnt']) . "\n";

$sql2 = "SELECT COUNT(*) AS cnt FROM thanhtoan tt JOIN hoadon h ON tt.maHD=h.maHD JOIN chisodien c ON h.maCSD=c.maCSD WHERE (c.nam<2026 OR (c.nam=2026 AND c.thang<5)) AND tt.trangthai='chuathanhtoan'";
$res2 = $conn->query($sql2);
$row2 = $res2->fetch_assoc();
echo 'payments_unpaid=' . intval($row2['cnt']) . "\n";

$conn->close();
