<?php

$host = 'sql203.infinityfree.com';
$dbname = 'if0_43203117_reviewernatin';
$username = 'if0_43203117';
$password = 'LeiBajande1';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    echo "<h1>Yey! Connected successfully sa database! 💚</h1>";
} catch (PDOException $e) {
    // Ipapalabas natin ang eksaktong error kung bakit nag-fail
    echo "<h1>Error Details:</h1>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
