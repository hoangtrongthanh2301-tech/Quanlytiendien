<?php

$keyDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'keys';
$privateKeyFile = $keyDirectory . DIRECTORY_SEPARATOR . 'blockchain_private.pem';
$publicKeyFile = $keyDirectory . DIRECTORY_SEPARATOR . 'blockchain_public.pem';
$force = in_array('--force', $argv, true);

if (!is_dir($keyDirectory) && !mkdir($keyDirectory, 0755, true) && !is_dir($keyDirectory)) {
    echo "Không tạo được thư mục lưu khóa: $keyDirectory\n";
    exit(1);
}

if (file_exists($privateKeyFile) && filesize($privateKeyFile) === 0) {
    unlink($privateKeyFile);
}
if (file_exists($publicKeyFile) && filesize($publicKeyFile) === 0) {
    unlink($publicKeyFile);
}
if (($fileExists = file_exists($privateKeyFile) || file_exists($publicKeyFile)) && !$force) {
    echo "Một hoặc cả hai file khóa đã tồn tại. Nếu bạn muốn tạo lại, xóa file cũ hoặc chạy với --force.\n";
    exit(1);
}
if ($fileExists && $force) {
    if (file_exists($privateKeyFile)) {
        unlink($privateKeyFile);
    }
    if (file_exists($publicKeyFile)) {
        unlink($publicKeyFile);
    }
}

$opensslCnf = getenv('OPENSSL_CONF');
if (!$opensslCnf || !file_exists($opensslCnf)) {
    $paths = [
        'C:\\xampp\\php\\extras\\openssl\\openssl.cnf',
        'C:\\xampp\\php\\extras\\ssl\\openssl.cnf',
        'C:\\xampp\\php\\windowsXamppPhp\\extras\\ssl\\openssl.cnf',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            $opensslCnf = $path;
            break;
        }
    }
}

if (!$opensslCnf || !file_exists($opensslCnf)) {
    echo "Không tìm thấy tệp cấu hình OpenSSL (openssl.cnf). Vui lòng kiểm tra cài đặt XAMPP.\n";
    exit(1);
}

putenv('OPENSSL_CONF=' . $opensslCnf);

$config = [
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'config' => $opensslCnf,
];

$keyPair = openssl_pkey_new($config);
if (!$keyPair) {
    echo "Không tạo được khoá RSA.\n";
    while ($err = openssl_error_string()) {
        echo "OpenSSL error: $err\n";
    }
    exit(1);
}

if (!openssl_pkey_export($keyPair, $privateKey)) {
    echo "Không xuất được private key bằng PHP OpenSSL. Chuyển sang OpenSSL CLI.\n";
    while ($err = openssl_error_string()) {
        echo "OpenSSL error: $err\n";
    }
    $privateKey = null;
} else {
    $publicKeyDetails = openssl_pkey_get_details($keyPair);
    if ($publicKeyDetails === false || !isset($publicKeyDetails['key'])) {
        echo "Không lấy được public key từ cặp khóa PHP. Chuyển sang OpenSSL CLI.\n";
        while ($err = openssl_error_string()) {
            echo "OpenSSL error: $err\n";
        }
        $privateKey = null;
    } else {
        $publicKey = $publicKeyDetails['key'];
    }
}

if (!isset($privateKey) || !isset($publicKey)) {
    $opensslExeCandidates = [
        'C:\\xampp\\apache\\bin\\openssl.exe',
        'C:\\xampp\\php\\extras\\openssl\\openssl.exe',
        'openssl'
    ];
    $opensslExe = null;
    foreach ($opensslExeCandidates as $candidate) {
        if (stripos($candidate, 'openssl') === 0) {
            $try = $candidate;
        } else {
            $try = $candidate;
        }
        if (file_exists($try) || $try === 'openssl') {
            $opensslExe = $try;
            break;
        }
    }

    if (!$opensslExe) {
        echo "Không tìm thấy openssl.exe để sử dụng CLI.\n";
        exit(1);
    }

    echo "Dùng openssl CLI: $opensslExe\n";
    $cmd1 = sprintf('%s genrsa -out %s 2048', escapeshellarg($opensslExe), escapeshellarg($privateKeyFile));
    exec($cmd1 . ' 2>&1', $output1, $ret1);
    if ($ret1 !== 0) {
        echo "Lỗi khi tạo private key bằng openssl.exe:\n" . implode("\n", $output1) . "\n";
        exit(1);
    }

    $cmd2 = sprintf('%s rsa -in %s -pubout -out %s', escapeshellarg($opensslExe), escapeshellarg($privateKeyFile), escapeshellarg($publicKeyFile));
    exec($cmd2 . ' 2>&1', $output2, $ret2);
    if ($ret2 !== 0) {
        echo "Lỗi khi tạo public key bằng openssl.exe:\n" . implode("\n", $output2) . "\n";
        exit(1);
    }

    echo "Đã tạo khóa blockchain bằng OpenSSL CLI.\n";
    echo "Private key: $privateKeyFile\n";
    echo "Public key: $publicKeyFile\n";
    exit(0);
}

if (file_put_contents($privateKeyFile, $privateKey) === false) {
    echo "Không ghi được file private key.\n";
    exit(1);
}
if (file_put_contents($publicKeyFile, $publicKey) === false) {
    echo "Không ghi được file public key.\n";
    exit(1);
}

chmod($privateKeyFile, 0600);
chmod($publicKeyFile, 0644);

echo "Đã tạo khóa blockchain.\n";
echo "Private key: $privateKeyFile\n";
echo "Public key: $publicKeyFile\n";
