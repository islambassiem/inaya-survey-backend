<?php

declare(strict_types=1);

$config = require __DIR__ . "/env.php";

if (!is_array($config) || !isset($config['db'])) {
    throw new RuntimeException("Invalid database configuration.");
}

$db = $config['db'];

$host = $db['host'];
$name = $db['name'];
$user = $db['user'];
$password = $db['password'];
$charset = $db['charset'] ?? 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$name};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
];

try {

    $pdo = new PDO(
        $dsn,
        $user,
        $password,
        $options
    );

} catch (PDOException $e) {

    error_log("Database connection failed: " . $e->getMessage());

    throw new RuntimeException(
        "Database connection failed."
    );
}
