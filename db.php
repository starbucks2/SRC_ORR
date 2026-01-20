<?php
// Web hosting credentials (update if your host provided different details)
$host = 'localhost';
$dbname = 'src_db';
$username = 'root';
$password = '';
$port = 3306;

// Build DSN with port and charset
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbname);

try {
    // Directly connect to existing database on hosting (no CREATE DATABASE here)
    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    // Log to file for hosting visibility
    @ini_set('log_errors', '1');
    @ini_set('error_log', __DIR__ . '/php_error.log');
    @error_log('DB connect error: ' . $e->getMessage());
    die('Database Connection Failed.');
}
?>
