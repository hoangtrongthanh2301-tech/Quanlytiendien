<?php

function validateCustomerCode(mysqli $conn, string $maKH): array
{
    $maKH = strtoupper(trim($maKH));

    if (!preg_match('/^([A-Z]{2})(\d{2})(\d{5})(\d{6})$/', $maKH, $matches)) {
        return [
            'valid' => false,
            'error' => 'Mã khách hàng phải có dạng mã điện lực + 2 số mã tỉnh + 5 số mã xã/phường + 6 số thứ tự.'
        ];
    }

    $maDienLuc = $matches[1];
    $maTinh = $matches[2];
    $maXa = $matches[3];

    try {
        $stmt = $conn->prepare(
            'SELECT 1 FROM don_vi_hanh_chinh WHERE ma_tinh = ? AND ma_xa = ? AND ma_dien_luc = ? LIMIT 1'
        );
    } catch (mysqli_sql_exception $exception) {
        $stmt = false;
    }
    if (!$stmt) {
        return [
            'valid' => false,
            'error' => 'Chưa có dữ liệu đơn vị hành chính mới trong database. Hãy chạy file seed_don_vi_hanh_chinh_2025.sql trước.'
        ];
    }

    $stmt->bind_param('sss', $maTinh, $maXa, $maDienLuc);
    $stmt->execute();
    $valid = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$valid) {
        return [
            'valid' => false,
            'error' => "Mã khách hàng {$maKH} không khớp mã tỉnh/xã hoặc mã điện lực trong dữ liệu hành chính."
        ];
    }

    return ['valid' => true, 'maKH' => $maKH];
}