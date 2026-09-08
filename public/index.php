<?php

use App\Core\Request;
use App\Core\Router;

$container = require_once __DIR__ . '/../app/bootstrap.php';
$request = new Request($_GET, $_POST, $_FILES, $_SERVER);
$router = new Router($request, $container);
$router->dispatch();
