<?php
// One-time script to create an admin account safely using password_hash.
// Usage: open http://localhost/FinalBecuran/seed_admin_account.php once, then delete this file.

session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "Admin seeding script\n\n";

try {
    // Ensure `admin` table exists with required columns
    $conn->exec("CREATE TABLE IF NOT EXISTS admin (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fullname VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Configure desired admin credentials here
    $fullname = isset($_GET['fullname']) ? trim($_GET['fullname']) : 'System Administrator';
    $email    = isset($_GET['email']) ? trim($_GET['email']) : 'admin@example.com';
    $passRaw  = isset($_GET['password']) ? (string)$_GET['password'] : 'Admin@12345';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo "Invalid email. Provide ?email=you@example.com\n";
        exit;
    }
    if ($passRaw === '') {
        http_response_code(400);
        echo "Password cannot be empty. Provide ?password=YourStrongPassword\n";
        exit;
    }

    // Check if admin with this email already exists
    $stmt = $conn->prepare("SELECT id FROM admin WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn()) {
        echo "Admin already exists for email: {$email}\n";
        echo "No changes made.\n";
        exit;
    }

    $hashed = password_hash($passRaw, PASSWORD_DEFAULT);
    $ins = $conn->prepare("INSERT INTO admin (fullname, email, password) VALUES (?, ?, ?)");
    $ins->execute([$fullname, $email, $hashed]);

    echo "Admin created successfully!\n";
    echo "Email: {$email}\n";
    echo "Temp Password: {$passRaw}\n";
    echo "\nSecurity reminder: Delete this file (seed_admin_account.php) after use.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Failed: ' . $e->getMessage() . "\n";
}
