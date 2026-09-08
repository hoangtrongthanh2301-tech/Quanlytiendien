<?php
session_start();

if (!empty($_SESSION['user'])) {
    $redirectPage = ($_SESSION['user']['quyen'] === 'admin') ? 'admin.html' : 'customer.php';
    header('Location: ' . $redirectPage, true, 302);
    exit;
}

header('Location: login.html', true, 302);
exit;
