<?php
require __DIR__ . '/../config.php';
$result = $conn->query('SELECT COUNT(*) AS c FROM blockchain_chisodien');
if ($result) {
    echo 'blockchain=' . $result->fetch_assoc()['c'] . "\n";
} else {
    echo 'blockchain error: ' . $conn->error . "\n";
}
$result = $conn->query('SELECT COUNT(*) AS c FROM chisodien');
if ($result) {
    echo 'chisodien=' . $result->fetch_assoc()['c'] . "\n";
} else {
    echo 'chisodien error: ' . $conn->error . "\n";
}
