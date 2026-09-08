<?php
require_once __DIR__ . '/require-admin.php';
require 'config.php';
header('Content-Type: application/json; charset=utf-8');

$csvPath = __DIR__ . '/analysis/output/anomalies.csv';
if (!file_exists($csvPath)) {
    echo json_encode(['success' => false, 'error' => 'Không tìm thấy file anomalies. Vui lòng chạy pipeline trước.']);
    exit;
}

$rows = [];
if (($handle = fopen($csvPath, 'r')) !== false) {
    $header = fgetcsv($handle);
    while (($data = fgetcsv($handle)) !== false) {
        $item = [];
        foreach ($header as $i => $col) {
            $item[$col] = $data[$i] ?? null;
        }
        // Cast types
        $item['last_kwh'] = isset($item['last_kwh']) ? floatval($item['last_kwh']) : null;
        $item['mean_kwh'] = isset($item['mean_kwh']) ? floatval($item['mean_kwh']) : null;
        $item['std_kwh'] = isset($item['std_kwh']) ? floatval($item['std_kwh']) : null;
        $item['is_anomaly'] = isset($item['is_anomaly']) ? (bool)$item['is_anomaly'] : false;
        $item['score'] = isset($item['score']) ? floatval($item['score']) : 0.0;
        $rows[] = $item;
    }
    fclose($handle);
}

echo json_encode(['success' => true, 'anomalies' => $rows]);
