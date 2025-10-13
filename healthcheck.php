<?php
// Temporary diagnostics - remove after debugging
header('Content-Type: text/plain');

$results = [];

function ok($msg){ echo "[OK] $msg\n"; }
function warn($msg){ echo "[WARN] $msg\n"; }
function fail($msg){ echo "[FAIL] $msg\n"; }

// PHP Version
$phpVersion = PHP_VERSION;
ok("PHP Version: $phpVersion");

// Required extensions
$exts = ['pdo', 'pdo_mysql', 'openssl', 'mbstring', 'json', 'curl'];
foreach ($exts as $ext) {
    if (extension_loaded($ext)) ok("Extension loaded: $ext");
    else fail("Extension missing: $ext");
}

// vendor/autoload.php
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) ok('Composer autoload present.');
else fail('Composer autoload missing: vendor/autoload.php not found. Upload vendor/ or run composer install.');

// Include db and test connection
try {
    require_once __DIR__ . '/db.php';
    // If we reached here, $conn (PDO) should exist
    if (isset($conn)) {
        $stmt = $conn->query('SELECT 1');
        $stmt->fetch();
        ok('Database connection (PDO) working.');
    } else {
        fail('db.php included but $conn not defined.');
    }
} catch (Throwable $e) {
    fail('Database connection failed: ' . $e->getMessage());
}

// Load .env presence
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) ok('.env found.'); else warn('.env not found.');

// Load mail config
try {
    $mailCfgPath = __DIR__ . '/config/mail.php';
    if (file_exists($mailCfgPath)) {
        $cfg = require $mailCfgPath;
        // Print non-sensitive parts
        ok('Mail config loaded.');
        echo "Mail host: " . ($cfg['host'] ?? 'n/a') . "\n";
        echo "Mail port: " . ($cfg['port'] ?? 'n/a') . "\n";
        echo "Mail secure: " . ($cfg['smtp_secure'] ?? 'n/a') . "\n";
        echo "App URL: " . ($cfg['app_url'] ?? 'n/a') . "\n";
        echo "From: " . ($cfg['from_email'] ?? 'n/a') . " (" . ($cfg['from_name'] ?? '') . ")\n";
    } else {
        fail('Mail config missing: config/mail.php not found.');
    }
} catch (Throwable $e) {
    fail('Mail config error: ' . $e->getMessage());
}

// Final suggestion
ok('If failures exist above, fix them and retry the site. Remove healthcheck.php when done.');
