<?php

$host = getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: getenv('MYSQL_HOST') ?: '127.0.0.1';
$user = getenv('DB_USER') ?: getenv('MYSQLUSER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: getenv('MYSQLPASSWORD') ?: '';
$database = getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: 'qltiendien';
$port = (int) (getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: 3306);

$connectTimeout = (int) (getenv('DB_CONNECT_TIMEOUT') ?: 3);
$readTimeout = (int) (getenv('DB_READ_TIMEOUT') ?: 8);

$conn = mysqli_init();
mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, $connectTimeout);
mysqli_options($conn, MYSQLI_OPT_READ_TIMEOUT, $readTimeout);
$connected = mysqli_real_connect($conn, $host, $user, $password, $database, $port);

if (!$connected) {
    die("Kết nối thất bại: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

define('BLOCKCHAIN_PRIVATE_KEY_FILE', getenv('BLOCKCHAIN_PRIVATE_KEY_FILE') ?: dirname(__DIR__) . '/storage/keys/blockchain_private.pem');
define('BLOCKCHAIN_PUBLIC_KEY_FILE', getenv('BLOCKCHAIN_PUBLIC_KEY_FILE') ?: dirname(__DIR__) . '/storage/keys/blockchain_public.pem');

// Set FACE_DESCRIPTOR_KEY in the web server environment for production deployments.

?>
