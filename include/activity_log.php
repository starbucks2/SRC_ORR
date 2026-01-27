<?php
// Simple activity logging helper
// Usage: require_once 'include/activity_log.php'; log_activity($conn, 'admin'|'subadmin'|'student'|'system', $actor_id, $action, $details_array);

if (!function_exists('log_activity')) {
    function log_activity(PDO $conn, string $actor_type, $actor_id, string $action, array $details = []) : void {
        try {
            // Ensure table exists
            $conn->exec("CREATE TABLE IF NOT EXISTS activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                actor_type VARCHAR(20) NOT NULL,
                actor_id VARCHAR(64) NULL,
                action VARCHAR(100) NOT NULL,
                details JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $stmt = $conn->prepare("INSERT INTO activity_logs (actor_type, actor_id, action, details) VALUES (?, ?, ?, ?)");
            $json = !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $stmt->execute([$actor_type, (string)$actor_id, $action, $json]);
        } catch (Throwable $e) {
            // Fail silently to avoid breaking UX; optionally uncomment to log server error
            // error_log('log_activity failed: ' . $e->getMessage());
        }
    }
}
