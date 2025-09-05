<?php
// config/db.php

// Environment variables for database configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'form_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root_password');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// PDO DSN for MySQL example
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = null; // Initialize pdo to null
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    // In a production environment, avoid exposing detailed error messages to the user.
    // For this example, we log and proceed, allowing email to still send if DB fails.
}
?>
