<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../blockchain.php';

if (php_sapi_name() !== 'cli') {
    echo "Vui lòng chạy script này từ dòng lệnh CLI.\n";
    exit(1);
}

set_time_limit(0);
$force = in_array('--force', $argv, true);

function cliLog($message) {
    echo $message . PHP_EOL;
}

if (!file_exists(getBlockChainPrivateKeyPath())) {
    cliLog('Lỗi: Chưa tồn tại khóa blockchain. Vui lòng chạy scripts/generate_blockchain_keys.php trước.');
    exit(1);
}

$blockCountResult = $conn->query('SELECT COUNT(*) AS cnt, MAX(maCSD) AS max_maCSD FROM blockchain_chisodien');
if (!$blockCountResult) {
    cliLog('Lỗi truy vấn blockchain_chisodien: ' . $conn->error);
    exit(1);
}
$blockCountRow = $blockCountResult->fetch_assoc();
$existingBlocks = intval($blockCountRow['cnt']);
$lastChainMaCSD = $blockCountRow['max_maCSD'] !== null ? intval($blockCountRow['max_maCSD']) : null;

if ($existingBlocks > 0) {
    cliLog("Đã tồn tại $existingBlocks block trong bảng blockchain_chisodien.\n");
    if ($force) {
        cliLog('--force được bật, bỏ qua kiểm tra chuỗi blockchain hiện tại.');
    } else {
        $chainResult = verifyBlockchainChain($conn);
        if (!$chainResult['ok']) {
            cliLog('Chuỗi blockchain hiện tại không hợp lệ. Dùng --force để tạo lại toàn bộ chuỗi từ dữ liệu lịch sử.');
            exit(1);
        }
    }
}

$missingQuery = "SELECT c.maCSD FROM chisodien c LEFT JOIN blockchain_chisodien b ON b.maCSD = c.maCSD WHERE b.maCSD IS NULL ORDER BY c.nam ASC, c.thang ASC, c.maCSD ASC LIMIT 1";
$missingResult = $conn->query($missingQuery);
if (!$missingResult) {
    cliLog('Lỗi truy vấn kiểm tra missing records: ' . $conn->error);
    exit(1);
}
$missingRow = $missingResult->fetch_assoc();
if ($missingRow) {
    $missingMaCSD = intval($missingRow['maCSD']);
    if ($existingBlocks > 0 && $missingMaCSD <= $lastChainMaCSD && !$force) {
        cliLog("Lỗi: Phát hiện block thiếu tại maCSD=$missingMaCSD trước khi kết thúc chuỗi hiện tại.\nVui lòng chạy lại với --force để tái tạo toàn bộ chuỗi từ lịch sử.");
        exit(1);
    }
}

if ($force) {
    cliLog('Đang xóa toàn bộ dữ liệu blockchain hiện tại và khởi tạo lại từ lịch sử.');
    if (!$conn->query('TRUNCATE TABLE blockchain_chisodien')) {
        cliLog('Lỗi truncate table: ' . $conn->error);
        exit(1);
    }
    $lastChainMaCSD = null;
}

$where = '';
$params = [];
if ($existingBlocks > 0 && !$force) {
    $where = 'WHERE c.maCSD > ?';
    $params[] = $lastChainMaCSD;
    cliLog("Đang thêm block mới từ maCSD > $lastChainMaCSD ...");
} else {
    cliLog('Đang tạo blockchain từ lịch sử dữ liệu chisodien...');
}

$sql = "SELECT c.maCSD, c.maKH, c.chisocu, c.chisomoi, c.thang, c.nam, c.ngaynhap FROM chisodien c $where ORDER BY c.maKH ASC, c.nam ASC, c.thang ASC, c.maCSD ASC";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    cliLog('Lỗi chuẩn bị truy vấn: ' . $conn->error);
    exit(1);
}
if (count($params) > 0) {
    $stmt->bind_param('i', $params[0]);
}
$stmt->execute();
$result = $stmt->get_result();
if (!$result) {
    cliLog('Lỗi truy vấn dữ liệu chisodien: ' . $stmt->error);
    exit(1);
}

$inserted = 0;
while ($row = $result->fetch_assoc()) {
    $ok = createBlockchainRecordRaw(
        $conn,
        intval($row['maCSD']),
        $row['maKH'],
        intval($row['chisocu']),
        intval($row['chisomoi']),
        intval($row['thang']),
        intval($row['nam']),
        date('Y-m-d H:i:s', strtotime($row['ngaynhap'])),
        'history'
    );
    if (!$ok) {
        cliLog('Lỗi khi ghi block cho maCSD=' . $row['maCSD']);
        exit(1);
    }
    $inserted++;
}

cliLog('Hoàn thành. Đã tạo thêm ' . $inserted . ' block.');
$conn->close();
