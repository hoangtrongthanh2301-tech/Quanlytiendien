<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');

function faceResponse($payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $payload['action'] ?? '';
$descriptor = $payload['descriptor'] ?? null;
$currentPassword = trim($payload['current_password'] ?? '');

if ($action !== 'verify_login' && !isset($_SESSION['user']['maKH'])) {
    faceResponse(['success' => false, 'error' => 'Phiên đăng nhập không hợp lệ. Vui lòng đăng nhập bằng mật khẩu trước.'], 401);
}
$keyMaterial = getenv('FACE_DESCRIPTOR_KEY') ?: hash('sha256', 'dev-only-face-descriptor-key|' . __DIR__, true);
$key = hash('sha256', $keyMaterial, true);

$columnCheck = $conn->query("SHOW COLUMNS FROM taikhoan LIKE 'face_descriptor'");
if (!$columnCheck || $columnCheck->num_rows === 0) {
    faceResponse(['success' => false, 'error' => 'Chưa cài đặt cấu trúc đăng nhập khuôn mặt. Hãy chạy sql/face_auth_migration.sql trong database qltiendien.'], 503);
}

function encryptFaceDescriptor($value, $key) {
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) return false;
    return base64_encode($iv . $tag . $ciphertext);
}

function decryptFaceDescriptor($value, $key) {
    $raw = base64_decode($value, true);
    if ($raw === false || strlen($raw) < 28) return false;
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ciphertext = substr($raw, 28);
    return openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
}

function validDescriptor($value) {
    return is_array($value) && count($value) === 128 && count(array_filter($value, 'is_numeric')) === 128;
}

function validPassword($password, $storedPassword) {
    return password_verify($password, $storedPassword) || hash_equals((string) $storedPassword, $password);
}

if ($action === 'verify_login') {
    if (!validDescriptor($descriptor)) faceResponse(['success' => false, 'error' => 'Mẫu khuôn mặt không hợp lệ.'], 422);
    $stmt = $conn->prepare("SELECT maKH, hovaten, email, sodienthoai, quyen, face_descriptor FROM taikhoan WHERE trangthai = 'hoatdong' AND face_descriptor IS NOT NULL");
    $stmt->execute();
    $users = $stmt->get_result();
    $stmt->close();
    $loginUser = null;
    $distance = INF;
    while ($candidate = $users->fetch_assoc()) {
        $stored = json_decode(decryptFaceDescriptor($candidate['face_descriptor'], $key), true);
        if (!validDescriptor($stored)) continue;
        $candidateDistance = 0;
        foreach ($stored as $index => $value) $candidateDistance += ($value - (float) $descriptor[$index]) ** 2;
        $candidateDistance = sqrt($candidateDistance);
        if ($candidateDistance < $distance) {
            $distance = $candidateDistance;
            $loginUser = $candidate;
        }
    }
    if (!$loginUser || $distance > 0.52) faceResponse(['success' => false, 'error' => 'Khuôn mặt không khớp với tài khoản đã đăng ký.'], 401);
        session_regenerate_id(true);
        $_SESSION['user'] = [
        'maKH' => $loginUser['maKH'], 'hovaten' => $loginUser['hovaten'],
        'email' => $loginUser['email'], 'sodienthoai' => $loginUser['sodienthoai'],
        'quyen' => $loginUser['quyen']
    ];
    faceResponse(['success' => true, 'verified' => true, 'role' => $loginUser['quyen'], 'distance' => round($distance, 4)]);
}

$maKH = $_SESSION['user']['maKH'];
$stmt = $conn->prepare('SELECT matkhau, face_descriptor, face_registered_at FROM taikhoan WHERE maKH = ? AND trangthai = \'hoatdong\' LIMIT 1');
$stmt->bind_param('s', $maKH);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($action === 'change_password') {
    $newPassword = trim($payload['new_password'] ?? '');
    if ($currentPassword === '' || $newPassword === '' || strlen($newPassword) < 6) faceResponse(['success' => false, 'error' => 'Mật khẩu mới phải có ít nhất 6 ký tự và cần nhập mật khẩu cũ.'], 422);
    if (!validPassword($currentPassword, $row['matkhau'])) faceResponse(['success' => false, 'error' => 'Mật khẩu cũ không đúng.'], 403);
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('UPDATE taikhoan SET matkhau = ? WHERE maKH = ?');
    $stmt->bind_param('ss', $hashedPassword, $maKH);
    $ok = $stmt->execute();
    $stmt->close();
    faceResponse(['success' => $ok, 'message' => $ok ? 'Đổi mật khẩu thành công.' : 'Không thể đổi mật khẩu.']);
}

if ($action === 'verify_password') {
    if ($currentPassword === '' || !validPassword($currentPassword, $row['matkhau'])) faceResponse(['success' => false, 'error' => 'Mật khẩu hiện tại không đúng.'], 403);
    faceResponse(['success' => true, 'message' => 'Mật khẩu chính xác.']);
}

if ($action === 'remove_face') {
    if ($currentPassword === '' || !validPassword($currentPassword, $row['matkhau'])) faceResponse(['success' => false, 'error' => 'Mật khẩu cũ không đúng.'], 403);
    $stmt = $conn->prepare('UPDATE taikhoan SET face_descriptor = NULL, face_registered_at = NULL WHERE maKH = ?');
    $stmt->bind_param('s', $maKH);
    $ok = $stmt->execute();
    $stmt->close();
    faceResponse(['success' => $ok, 'message' => $ok ? 'Đã xóa đăng ký khuôn mặt.' : 'Không thể xóa đăng ký khuôn mặt.']);
}

if ($action === 'register') {
    if ($currentPassword === '' || !validPassword($currentPassword, $row['matkhau'])) faceResponse(['success' => false, 'error' => 'Mật khẩu cũ không đúng.'], 403);
    if (!validDescriptor($descriptor)) faceResponse(['success' => false, 'error' => 'Mẫu khuôn mặt không hợp lệ.'], 422);
    $encrypted = encryptFaceDescriptor(json_encode(array_map('floatval', $descriptor)), $key);
    if ($encrypted === false) faceResponse(['success' => false, 'error' => 'Không thể mã hóa mẫu khuôn mặt.'], 500);
    $stmt = $conn->prepare('UPDATE taikhoan SET face_descriptor = ?, face_registered_at = NOW() WHERE maKH = ?');
    $stmt->bind_param('ss', $encrypted, $maKH);
    $ok = $stmt->execute();
    $stmt->close();
    faceResponse(['success' => $ok, 'message' => $ok ? 'Đã đăng ký khuôn mặt cho tài khoản admin.' : 'Không thể lưu mẫu khuôn mặt.']);
}

if ($action === 'status') {
    faceResponse(['success' => true, 'registered' => !empty($row['face_descriptor']), 'registered_at' => $row['face_registered_at'] ?? null]);
}

if ($action === 'verify') {
    if (!validDescriptor($descriptor) || empty($row['face_descriptor'])) faceResponse(['success' => false, 'error' => 'Tài khoản chưa đăng ký khuôn mặt hoặc mẫu không hợp lệ.'], 422);
    $stored = json_decode(decryptFaceDescriptor($row['face_descriptor'], $key), true);
    if (!validDescriptor($stored)) faceResponse(['success' => false, 'error' => 'Không đọc được mẫu khuôn mặt đã lưu.'], 500);
    $distance = 0;
    foreach ($stored as $index => $value) $distance += ($value - (float) $descriptor[$index]) ** 2;
    $distance = sqrt($distance);
    faceResponse(['success' => $distance <= 0.52, 'verified' => $distance <= 0.52, 'distance' => round($distance, 4), 'error' => $distance <= 0.52 ? null : 'Khuôn mặt không khớp.']);
}

faceResponse(['success' => false, 'error' => 'Hành động không được hỗ trợ.'], 400);