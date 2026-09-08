<?php
$path = 'C:/xampp/php/extras/openssl/openssl.cnf';
putenv('OPENSSL_CONF=' . $path);
echo "OPENSSL_CONF='" . getenv('OPENSSL_CONF') . "'\n";
$config1 = array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $path);
$res1 = openssl_pkey_new($config1);
var_dump($res1);
if ($res1) {
    $priv = '';
    $ok = openssl_pkey_export($res1, $priv);
    echo 'export1=' . ($ok ? 'yes' : 'no') . PHP_EOL;
    if ($ok) {
        echo substr($priv,0,20) . PHP_EOL;
    }
}
while ($err = openssl_error_string()) {
    echo "ERR1: $err\n";
}

$config2 = array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA);
$res2 = openssl_pkey_new($config2);
var_dump($res2);
if ($res2) {
    $priv2 = '';
    $ok2 = openssl_pkey_export($res2, $priv2);
    echo 'export2=' . ($ok2 ? 'yes' : 'no') . PHP_EOL;
    if ($ok2) {
        echo substr($priv2,0,20) . PHP_EOL;
    }
}
while ($err = openssl_error_string()) {
    echo "ERR2: $err\n";
}
