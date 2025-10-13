<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$student_id = (int)$_SESSION['student_id'];
$paper_id = isset($_POST['paper_id']) ? (int)$_POST['paper_id'] : 0;
if ($paper_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid paper ID']);
    exit;
}

try {
    // Ensure bookmarks table exists
    $conn->exec("CREATE TABLE IF NOT EXISTS bookmarks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        paper_id INT NOT NULL,
        bookmarked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_bookmark (student_id, paper_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Check current state
    $stmt = $conn->prepare('SELECT id FROM bookmarks WHERE student_id = ? AND paper_id = ?');
    $stmt->execute([$student_id, $paper_id]);
    $existingId = $stmt->fetchColumn();

    if ($existingId) {
        // Remove bookmark
        $del = $conn->prepare('DELETE FROM bookmarks WHERE id = ?');
        $del->execute([$existingId]);
        echo json_encode(['success' => true, 'bookmarked' => false]);
        exit;
    } else {
        // Add bookmark
        $ins = $conn->prepare('INSERT INTO bookmarks (student_id, paper_id) VALUES (?, ?)');
        $ins->execute([$student_id, $paper_id]);
        echo json_encode(['success' => true, 'bookmarked' => true]);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
