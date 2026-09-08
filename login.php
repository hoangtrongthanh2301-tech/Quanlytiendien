<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit;
}

$login_type = $_POST['login_type'] ?? 'account';
$password = trim($_POST['password'] ?? '');

if ($login_type === 'phone') {
    $identifier = trim($_POST['phone'] ?? '');
    $field = 'sodienthoai';
} else {
    $identifier = trim($_POST['username'] ?? '');
    $field = 'maKH';
}

if ($identifier === '' || $password === '') {
    $error = 'Vui lòng nhập đầy đủ thông tin đăng nhập.';
    header('Location: login.html?error=' . urlencode($error) . '&login_type=' . urlencode($login_type));
    exit;
}

$sql = "SELECT maKH, hovaten, email, sodienthoai, quyen, trangthai, matkhau FROM taikhoan WHERE $field = ? AND trangthai = 'hoatdong' LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die('Lỗi SQL: ' . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, 's', $identifier);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
$passwordValid = $user && (password_verify($password, $user['matkhau']) || hash_equals((string) $user['matkhau'], $password));

if ($passwordValid) {
    if (!password_get_info($user['matkhau'])['algo']) {
        $upgradedPassword = password_hash($password, PASSWORD_DEFAULT);
        $upgradeStmt = mysqli_prepare($conn, 'UPDATE taikhoan SET matkhau = ? WHERE maKH = ?');
        mysqli_stmt_bind_param($upgradeStmt, 'ss', $upgradedPassword, $user['maKH']);
        mysqli_stmt_execute($upgradeStmt);
        mysqli_stmt_close($upgradeStmt);
    }
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'maKH' => $user['maKH'],
        'hovaten' => $user['hovaten'],
        'email' => $user['email'],
        'sodienthoai' => $user['sodienthoai'],
        'quyen' => $user['quyen'],
    ];

    if ($user['quyen'] === 'admin') {
        header('Location: admin.html');
    } else {
        header('Location: customer.php');
    }
    exit;
}

$error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
header('Location: login.html?error=' . urlencode($error) . '&login_type=' . urlencode($login_type));
exit;
