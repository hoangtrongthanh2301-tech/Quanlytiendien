<?php
require_once dirname(__DIR__) . '/config.php';

// Truncate table
$conn->query("TRUNCATE TABLE thongketiendien");

// Lấy tất cả bản ghi chisodien cùng thông tin hoadon
$sql = "SELECT c.maCSD, c.maKH, c.chisocu, c.chisomoi, c.thang, c.nam, h.maHD
        FROM chisodien c
        JOIN hoadon h ON c.maCSD = h.maCSD";

$res = $conn->query($sql);
if (!$res) {
    echo "Lỗi truy vấn chisodien: " . $conn->error;
    exit;
}

function getTier5Price($conn, $date) {
    $sql = "SELECT g1.dongia
            FROM giadien g1
            INNER JOIN (
              SELECT bac, MAX(ngayapdung) AS max_date
              FROM giadien
              WHERE ngayapdung <= ? AND bac = 5
              GROUP BY bac
            ) g2 ON g1.bac = g2.bac AND g1.ngayapdung = g2.max_date
            WHERE g1.bac = 5
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? floatval($row['dongia']) : null;
}

function getLatestRatesForDate($conn, $date) {
    $sql = "SELECT g1.bac, g1.sanluong, g1.dongia
            FROM giadien g1
            INNER JOIN (
              SELECT bac, MAX(ngayapdung) AS max_date
              FROM giadien
              WHERE ngayapdung <= ? AND bac <= 4
              GROUP BY bac
            ) g2 ON g1.bac = g2.bac AND g1.ngayapdung = g2.max_date
            WHERE g1.bac <= 4
            ORDER BY g1.bac ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo "Lỗi chuẩn bị truy vấn giá tại ngày $date: " . $conn->error . "\n";
        exit;
    }
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $tiers = [];
    while ($r = $result->fetch_assoc()) {
        $tiers[] = [
            'bac' => intval($r['bac']),
            'sanluong' => intval($r['sanluong']),
            'dongia' => floatval($r['dongia'])
        ];
    }
    $stmt->close();
    return $tiers;
}

function getFirstChangeDateInMonth($conn, $monthStart, $monthEnd) {
    $stmt = $conn->prepare(
        "SELECT DISTINCT ngayapdung FROM giadien WHERE ngayapdung > ? AND ngayapdung <= ? ORDER BY ngayapdung ASC LIMIT 1"
    );
    if (!$stmt) {
        echo "Lỗi truy vấn ngày thay đổi giá: " . $conn->error . "\n";
        exit;
    }
    $stmt->bind_param('ss', $monthStart, $monthEnd);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['ngayapdung'] : null;
}

function splitConsumptionByTiers($consumption, $tiers, $tier5Price = null) {
    $rows = [];
    $remaining = $consumption;
    $lastTierPrice = null;
    
    foreach ($tiers as $t) {
        $lastTierPrice = $t['dongia'];
        if ($remaining <= 0) break;
        $capacity = $t['sanluong'] > 0 ? $t['sanluong'] : $remaining;
        $kwhThis = min($remaining, $capacity);
        if ($kwhThis <= 0) continue;
        $rows[] = [
            'bac' => $t['bac'],
            'dongia' => $t['dongia'],
            'sanluong' => $kwhThis,
            'thanhtien' => $kwhThis * $t['dongia']
        ];
        $remaining -= $kwhThis;
    }
    
    // Nếu còn dư sau 4 bậc, tính theo giá bậc 5
    if ($remaining > 0 && $lastTierPrice !== null) {
        // Nếu truyền giá bậc 5 từ ngoài (tier5Price), dùng nó. Nếu không thì dùng giá bậc cuối cùng
        $price = $tier5Price !== null ? $tier5Price : $lastTierPrice;
        $rows[] = [
            'bac' => 5,
            'dongia' => $price,
            'sanluong' => $remaining,
            'thanhtien' => $remaining * $price
        ];
    }
    
    return $rows;
}

function insertBreakdownRows($stmt, $maKH, $maCSD, $rows) {
    foreach ($rows as $row) {
        $stmt->bind_param('siiddi', $maKH, $maCSD, $row['bac'], $row['dongia'], $row['sanluong'], $row['thanhtien']);
        if (!$stmt->execute()) {
            echo "Lỗi ghi thongketiendien cho maCSD=$maCSD bậc {$row['bac']}: " . $stmt->error . "\n";
            exit;
        }
    }
}

// Chèn từng phân bậc cho mỗi chisodien
$insertStmt = $conn->prepare("INSERT INTO thongketiendien (maKH, maCSD, bacthang, dongia, sanluong, thanhtien) VALUES (?, ?, ?, ?, ?, ?)");
if (!$insertStmt) {
    echo "Không thể chuẩn bị truy vấn ghi thongketiendien: " . $conn->error . "\n";
    exit;
}

while ($row = $res->fetch_assoc()) {
    $maCSD = intval($row['maCSD']);
    $maKH = $row['maKH'];
    $consumption = intval($row['chisomoi']) - intval($row['chisocu']);
    $monthStart = sprintf('%04d-%02d-01', intval($row['nam']), intval($row['thang']));
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    $changeDate = getFirstChangeDateInMonth($conn, $monthStart, $monthEnd);

    if ($changeDate) {
        $totalDays = intval(date('t', strtotime($monthStart)));
        $changeDay = intval(date('j', strtotime($changeDate)));
        $daysBefore = max(0, $changeDay - 1);
        $beforeCons = $totalDays > 0 ? (int) round($consumption * $daysBefore / $totalDays) : 0;
        $afterCons = max(0, $consumption - $beforeCons);

        if ($beforeCons > 0) {
            $oldDate = date('Y-m-d', strtotime($changeDate . ' -1 day'));
            $oldTiers = getLatestRatesForDate($conn, $oldDate);
            $tier5PriceOld = getTier5Price($conn, $oldDate);
            insertBreakdownRows($insertStmt, $maKH, $maCSD, splitConsumptionByTiers($beforeCons, $oldTiers, $tier5PriceOld));
        }

        if ($afterCons > 0) {
            $newTiers = getLatestRatesForDate($conn, $changeDate);
            $tier5PriceNew = getTier5Price($conn, $changeDate);
            insertBreakdownRows($insertStmt, $maKH, $maCSD, splitConsumptionByTiers($afterCons, $newTiers, $tier5PriceNew));
        }
    } else {
        $currentTiers = getLatestRatesForDate($conn, $monthStart);
        $tier5Price = getTier5Price($conn, $monthStart);
        insertBreakdownRows($insertStmt, $maKH, $maCSD, splitConsumptionByTiers($consumption, $currentTiers, $tier5Price));
    }
}

$insertStmt->close();
$conn->close();

echo "Đã rebuild thongketiendien từ dữ liệu chisodien.\n";
?>