<?php
if (ob_get_level() === 0) {
    ob_start();
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (isset($_SESSION['user']['quyen']) && $_SESSION['user']['quyen'] === 'admin') {
    return;
}

$isApiRequest = ($_SERVER['HTTP_ACCEPT'] ?? '') === ''
    || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
    || str_ends_with(strtolower($_SERVER['SCRIPT_NAME'] ?? ''), '.php');

if ($isApiRequest) {
    http_response_code(isset($_SESSION['user']) ? 403 : 401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => isset($_SESSION['user']) ? 'Bạn không có quyền quản trị.' : 'Vui lòng đăng nhập tài khoản quản trị.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Location: login.html');
exit;
