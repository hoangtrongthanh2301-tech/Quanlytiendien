<?php
require_once __DIR__ . '/require-admin.php';
require 'config.php';
$container = require_once __DIR__ . '/app/bootstrap.php';
$hashService = $container->get('hash_service');
header('Content-Type: application/json; charset=utf-8');

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body) || !isset($body['readings']) || !is_array($body['readings'])) {
    echo json_encode(['success' => false, 'error' => 'Dữ liệu không hợp lệ.']);
    exit;
}

$readings = $body['readings'];
if (count($readings) === 0) {
    echo json_encode(['success' => false, 'error' => 'Không có chỉ số nào để lưu.']);
    exit;
}

$hashColumnExists = false;
$hashColumnResult = $conn->query("SHOW COLUMNS FROM `chisodien` LIKE 'hash_record'");
if ($hashColumnResult && $hashColumnResult->num_rows > 0) {
    $hashColumnExists = true;
}

$selectCurrent = $conn->prepare("SELECT maCSD, chisocu, chisomoi, ngaynhap FROM chisodien WHERE maKH = ? AND thang = ? AND nam = ? LIMIT 1");
$selectPrevious = $conn->prepare("SELECT chisomoi FROM chisodien WHERE maKH = ? AND (nam < ? OR (nam = ? AND thang < ?)) ORDER BY nam DESC, thang DESC, maCSD DESC LIMIT 1");
$insertSql = "INSERT INTO chisodien (maKH, chisocu, chisomoi, thang, nam" . ($hashColumnExists ? ", hash_record" : "") . ") VALUES (?, ?, ?, ?, ?" . ($hashColumnExists ? ", ?" : "") . ")";
$insertStmt = $conn->prepare($insertSql);
$updateSql = "UPDATE chisodien SET chisocu = ?, chisomoi = ?" . ($hashColumnExists ? ", hash_record = ?" : "") . " WHERE maCSD = ?";
$updateStmt = $conn->prepare($updateSql);

$inserted = 0;
$updated = 0;
$skipped = 0;
$skippedReasons = [];
$changedPeriods = [];
foreach ($readings as $item) {
    if (!isset($item['maKH'], $item['thang'], $item['nam'])) {
        continue;
    }

    $maKH = trim($item['maKH']);
    $thang = intval($item['thang']);
    $nam = intval($item['nam']);
    $rawChisocu = isset($item['chisocu']) ? trim((string)$item['chisocu']) : '';
    $rawChisomoi = isset($item['chisomoi']) ? trim((string)$item['chisomoi']) : '';

    if ($maKH === '' || $thang < 1 || $thang > 12 || $nam < 2000) {
        continue;
    }

    $selectCurrent->bind_param('sii', $maKH, $thang, $nam);
    $selectCurrent->execute();
    $currentResult = $selectCurrent->get_result();
    $currentRow = $currentResult->fetch_assoc();
    $exists = $currentRow !== null;

    $chisocu = ($rawChisocu !== '') ? intval($rawChisocu) : null;
    $chisomoi = ($rawChisomoi !== '') ? intval($rawChisomoi) : null;

    if ($chisomoi === null) {
        continue;
    }
    if ($chisocu === null) {
        $selectPrevious->bind_param('siii', $maKH, $nam, $nam, $thang);
        $selectPrevious->execute();
        $prevResult = $selectPrevious->get_result();
        $prevRow = $prevResult->fetch_assoc();
        if ($prevRow && $prevRow['chisomoi'] !== null) {
            $chisocu = intval($prevRow['chisomoi']);
        }
    }

    if ($chisocu === null) {
        continue;
    }
    if ($chisomoi < $chisocu) {
        continue;
    }

    $conn->begin_transaction();
    $newPeriodKey = "$thang-$nam";

    if ($exists) {
        $existingId = intval($currentRow['maCSD']);
        $existingChisocu = intval($currentRow['chisocu']);
        $existingChisomoi = intval($currentRow['chisomoi']);
        $ngaynhap = $currentRow['ngaynhap'];
        if ($existingChisocu === $chisocu && $existingChisomoi === $chisomoi) {
            $skipped++;
            $skippedReasons[] = "Bản ghi tháng $thang/$nam của $maKH không thay đổi.";
            $conn->rollback();
            continue;
        }

        $hashValue = $hashColumnExists ? $hashService->hashRecord([
            'maKH' => $maKH,
            'chisocu' => $chisocu,
            'chisomoi' => $chisomoi,
            'thang' => $thang,
            'nam' => $nam,
            'ngaynhap' => $ngaynhap,
        ]) : null;

        if ($hashColumnExists) {
            $updateStmt->bind_param('iisi', $chisocu, $chisomoi, $hashValue, $existingId);
        } else {
            $updateStmt->bind_param('iii', $chisocu, $chisomoi, $existingId);
        }

        if ($updateStmt->execute()) {
            $conn->commit();
            $updated++;
            $changedPeriods[$newPeriodKey] = ['thang' => $thang, 'nam' => $nam];
        } else {
            $conn->rollback();
        }
        continue;
    }

    $ngaynhap = date('Y-m-d');
    $hashValue = $hashColumnExists ? $hashService->hashRecord([
        'maKH' => $maKH,
        'chisocu' => $chisocu,
        'chisomoi' => $chisomoi,
        'thang' => $thang,
        'nam' => $nam,
        'ngaynhap' => $ngaynhap,
    ]) : null;

    if ($hashColumnExists) {
        $insertStmt->bind_param('siiiss', $maKH, $chisocu, $chisomoi, $thang, $nam, $hashValue);
    } else {
        $insertStmt->bind_param('siiii', $maKH, $chisocu, $chisomoi, $thang, $nam);
    }

    if ($insertStmt->execute()) {
        $newId = $conn->insert_id;
        if (!createBlockchainRecord($conn, $newId, $maKH, $chisocu, $chisomoi, $thang, $nam, 'admin')) {
            $conn->rollback();
            $errors[] = "Không tạo được blockchain cho bản ghi mới $maKH $thang/$nam.";
            continue;
        }
        $inserted++;
        $conn->commit();
    } else {
        $conn->rollback();
    }
}

if ($inserted === 0 && $updated === 0) {
    $response = ['success' => false, 'error' => 'Không có bản ghi mới nào được lưu.'];
    if ($skipped > 0) {
        $response['skipped'] = $skipped;
    }
    echo json_encode($response);
} else {
    $response = ['success' => true, 'inserted' => $inserted, 'updated' => $updated];
    if ($skipped > 0) {
        $response['skipped'] = $skipped;
    }
    if (!empty($errors)) {
        $response['errors'] = $errors;
    }
    echo json_encode($response);
}

$conn->close();
