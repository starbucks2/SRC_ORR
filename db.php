<?php
// Load environment variables from .env file
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments and lines without =
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;

        // Split only on the first = to preserve = in values
        $pos = strpos($line, '=');
        $key = trim(substr($line, 0, $pos));
        $value = substr($line, $pos + 1);

        // Handle quoted values (remove surrounding quotes but keep content intact)
        $value = trim($value);
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")
        ) {
            $value = substr($value, 1, -1);
        }

        if ($key !== '' && !array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
    }
}

// Database credentials from .env (with fallbacks for local development)
$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'src_db';
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? '';
$port = (int)($_ENV['DB_PORT'] ?? 3306);

// Build DSN with port and charset
// Build DSN helper
$baseDsn = 'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4';

// Attempt connection with retries for common hosting mismatches
$attempts = [];
$attempts[] = sprintf($baseDsn, $host, $port, $dbname);
// If host is localhost try 127.0.0.1 as some hosts bind differently
if (strtolower($host) === 'localhost') {
    $attempts[] = sprintf($baseDsn, '127.0.0.1', $port, $dbname);
}
// If dbname has uppercase letters, try lowercase variant (some filesystems/hosts are case-sensitive)
if ($dbname !== strtolower($dbname)) {
    $attempts[] = sprintf($baseDsn, $host, $port, strtolower($dbname));
    if (strtolower($host) === 'localhost') {
        $attempts[] = sprintf($baseDsn, '127.0.0.1', $port, strtolower($dbname));
    }
}

$conn = null;
$lastException = null;
foreach ($attempts as $idx => $dsn) {
    try {
        $conn = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // Successful connect — break out
        $lastException = null;
        break;
    } catch (PDOException $e) {
        $lastException = $e;
        @ini_set('log_errors', '1');
        @ini_set('error_log', __DIR__ . '/php_error.log');
        @error_log(sprintf("DB connect attempt %d failed (DSN=%s): %s", $idx + 1, $dsn, $e->getMessage()));
        // continue to next attempt
    }
}

if (!$conn) {
    // All attempts failed — log and surface a helpful message for admins
    $errMsg = $lastException ? $lastException->getMessage() : 'Unknown error';
    @error_log('DB connect error (final): ' . $errMsg);
    $conn = null;
    $maskedUser = isset($username) ? preg_replace('/.(?=.{2})/u', '*', $username) : 'unknown';
    $GLOBALS['DB_CONNECT_ERROR'] = 'Database Connection Issue: ' . $errMsg . ' — Please verify DB credentials and host in ' . __DIR__ . '/.env. Current DB user: ' . $maskedUser;
}
