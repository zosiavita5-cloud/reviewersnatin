
<?php

$host = 'sql203.infinityfree.com';
$dbname = 'if0_43203117_reviewernatin';
$username = 'if0_43203117';
$password = 'LeiBajande1';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log($e->getMessage());
    exit('Database connection failed. Please check the database configuration.');
}
