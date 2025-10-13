<?php
session_start();
header('Content-Type: application/json');

// Only admins can clear logs
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../db.php';

try {
    // Ensure table exists (defensive)
    $conn->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        actor_type VARCHAR(20) NOT NULL,
        actor_id VARCHAR(64) NULL,
        action VARCHAR(100) NOT NULL,
        details JSON NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Clear all logs
    $conn->exec('TRUNCATE TABLE activity_logs');

    // Reset unread marker
    $_SESSION['logs_last_view'] = date('Y-m-d H:i:s');

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to clear logs']);
}
