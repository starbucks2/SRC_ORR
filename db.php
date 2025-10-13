<?php
$host = 'localhost'; // Change if using a remote database
$dbname = 'src_capstone_repository'; // New database name
$username = 'root'; // Replace with your database username
$password = ''; // Replace with your database password

try {
    // Ensure the database exists; create it if it doesn't
    $serverPdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $serverPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Connect to the target database
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>
