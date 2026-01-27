<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

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

    $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 50;
    $stmt = $conn->prepare("SELECT id, actor_type, actor_id, action, details, created_at FROM activity_logs ORDER BY created_at DESC, id DESC LIMIT :lim");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $lastView = $_SESSION['logs_last_view'] ?? null;
    $unread = 0;
    if ($lastView) {
        foreach ($logs as $lg) {
            if (strtotime($lg['created_at']) > strtotime($lastView)) { $unread++; }
        }
    } else {
        $unread = count($logs);
    }

    echo json_encode([
        'status' => 'ok',
        'unread' => $unread,
        'logs' => $logs,
        'lastView' => $lastView,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
