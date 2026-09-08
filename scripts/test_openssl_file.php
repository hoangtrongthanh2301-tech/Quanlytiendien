<?php
$path = 'C:/xampp/php/extras/openssl/openssl.cnf';
echo 'path=' . $path . PHP_EOL;
echo 'file_exists=' . (file_exists($path) ? 'yes' : 'no') . PHP_EOL;
echo 'is_readable=' . (is_readable($path) ? 'yes' : 'no') . PHP_EOL;
echo 'realpath=' . realpath($path) . PHP_EOL;
