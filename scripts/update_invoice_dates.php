<?php
require_once __DIR__ . '/../config.php';

$sql = "UPDATE hoadon h
JOIN chisodien c ON h.maCSD = c.maCSD
SET h.ngaytao = CONCAT(c.nam, '-', LPAD(c.thang, 2, '0'), '-15 12:00:00'),
    h.hansudung = DATE_ADD(CONCAT(c.nam, '-', LPAD(c.thang, 2, '0'), '-15'), INTERVAL 1 MONTH)
WHERE h.ngaytao <> CONCAT(c.nam, '-', LPAD(c.thang, 2, '0'), '-15 12:00:00')
   OR h.hansudung <> DATE_ADD(CONCAT(c.nam, '-', LPAD(c.thang, 2, '0'), '-15'), INTERVAL 1 MONTH)";

if (!mysqli_query($conn, $sql)) {
    echo "Lỗi cập nhật hoá đơn: " . mysqli_error($conn) . "\n";
    exit(1);
}

$updated = mysqli_affected_rows($conn);
echo "Đã cập nhật $updated hoá đơn.\n";

$conn->close();
