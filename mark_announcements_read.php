<?php
session_start();
header('Content-Type: application/json');

require_once 'db.php';

if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$student_id = (int)$_SESSION['student_id'];
$strand = $_SESSION['strand'] ?? '';

try {
    // Create read-tracking table if it doesn't exist
    $conn->exec("CREATE TABLE IF NOT EXISTS student_announcement_reads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        announcement_id INT NOT NULL,
        read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_student_announcement (student_id, announcement_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Determine which announcements are relevant to this student
    $stmt = $conn->prepare("SELECT id FROM announcements WHERE (strand = ? OR strand IS NULL OR strand = '')");
    $stmt->execute([$strand]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$ids || count($ids) === 0) {
        echo json_encode(['success' => true, 'marked' => 0]);
        exit;
    }

    // Insert ignore for each id
    $ins = $conn->prepare("INSERT IGNORE INTO student_announcement_reads (student_id, announcement_id) VALUES (?, ?)");
    $marked = 0;
    foreach ($ids as $aid) {
        if ($ins->execute([$student_id, (int)$aid])) {
            $marked += ($ins->rowCount() > 0) ? 1 : 0;
        }
    }

    echo json_encode(['success' => true, 'marked' => $marked]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
