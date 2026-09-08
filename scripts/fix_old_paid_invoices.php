<?php
require_once dirname(__DIR__) . '/config.php';

$targetCondition = "(c.nam < 2026 OR (c.nam = 2026 AND c.thang < 5))";

$dropTriggerSql = "DROP TRIGGER IF EXISTS trg_capnhat_hoadon";
$invoiceUpdateSql = "UPDATE hoadon h
    JOIN chisodien c ON h.maCSD = c.maCSD
    SET h.trangthai = 'dathanhtoan', h.ngaytao = CONCAT(c.nam, '-', LPAD(c.thang, 2, '0'), '-15 12:00:00')
    WHERE $targetCondition AND h.trangthai <> 'dathanhtoan'";
$tempTableSql = "CREATE TEMPORARY TABLE temp_paid_hd AS
    SELECT h.maHD
    FROM hoadon h
    JOIN chisodien c ON h.maCSD = c.maCSD
    WHERE $targetCondition";
$paymentUpdateSql = "UPDATE thanhtoan tt
    JOIN temp_paid_hd p ON tt.maHD = p.maHD
    SET tt.trangthai = 'dathanhtoan'
    WHERE tt.trangthai = 'chuathanhtoan'";
$createTriggerSql = "CREATE TRIGGER trg_capnhat_hoadon AFTER UPDATE ON thanhtoan FOR EACH ROW BEGIN
    IF NEW.trangthai = 'dathanhtoan' THEN
        UPDATE hoadon
        SET trangthai = 'dathanhtoan'
        WHERE maHD = NEW.maHD;
    END IF;
END";

if (!$conn->query($dropTriggerSql)) {
    echo "Lỗi xóa trigger: " . $conn->error . "\n";
    exit(1);
}

if (!$conn->query($invoiceUpdateSql)) {
    echo "Lỗi cập nhật hoadon: " . $conn->error . "\n";
    exit(1);
}
$updatedInvoices = $conn->affected_rows;

if (!$conn->query($tempTableSql)) {
    echo "Lỗi tạo bảng tạm: " . $conn->error . "\n";
    exit(1);
}

if (!$conn->query($paymentUpdateSql)) {
    echo "Lỗi cập nhật thanhtoan: " . $conn->error . "\n";
    exit(1);
}
$updatedPayments = $conn->affected_rows;

if (!$conn->query($createTriggerSql)) {
    echo "Lỗi tạo lại trigger: " . $conn->error . "\n";
    exit(1);
}

$remainingInvoiceSql = "SELECT COUNT(*) AS cnt FROM hoadon h JOIN chisodien c ON h.maCSD = c.maCSD WHERE $targetCondition AND h.trangthai = 'chuathanhtoan'";
$remainingInvoiceRes = $conn->query($remainingInvoiceSql);
$remainingInvoices = $remainingInvoiceRes ? intval($remainingInvoiceRes->fetch_assoc()['cnt']) : -1;

$remainingPaymentSql = "SELECT COUNT(*) AS cnt FROM thanhtoan tt JOIN temp_paid_hd p ON tt.maHD = p.maHD WHERE tt.trangthai = 'chuathanhtoan'";
$remainingPaymentRes = $conn->query($remainingPaymentSql);
$remainingPayments = $remainingPaymentRes ? intval($remainingPaymentRes->fetch_assoc()['cnt']) : -1;

echo "Updated invoices: $updatedInvoices\n";
echo "Updated payments: $updatedPayments\n";
echo "Remaining unpaid invoices before 05/2026: $remainingInvoices\n";
echo "Remaining unpaid payments before 05/2026: $remainingPayments\n";

$conn->close();
?>
