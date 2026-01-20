<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

try {
    // Ensure table exists
    $conn->exec("CREATE TABLE IF NOT EXISTS academic_years (
        id INT AUTO_INCREMENT PRIMARY KEY,
        span VARCHAR(15) NOT NULL UNIQUE, -- e.g., 2026-2027
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // If empty, seed with a sensible default range (current year down to 2000)
    $count = (int)$conn->query('SELECT COUNT(*) FROM academic_years')->fetchColumn();
    if ($count === 0) {
        $cur = (int)date('Y');
        $ins = $conn->prepare('INSERT IGNORE INTO academic_years(span, is_active) VALUES(?, 1)');
        for ($y = $cur + 1; $y >= 2000; $y--) { // include next year span too
            $span = ($y-1) . '-' . $y;
            $ins->execute([$span]);
        }
    }

    // Fetch active spans sorted descending by start year
    $stmt = $conn->query("SELECT span, is_active FROM academic_years WHERE is_active = 1 ORDER BY SUBSTRING_INDEX(span,'-',1) DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'data' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
