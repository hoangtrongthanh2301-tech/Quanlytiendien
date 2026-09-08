<?php
session_start();

if (($_SESSION['user']['quyen'] ?? '') !== 'admin') {
    header('Location: login.html');
    exit;
}

readfile(__DIR__ . '/admin.html');
