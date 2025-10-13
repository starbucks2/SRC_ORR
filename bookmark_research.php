<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Only students can bookmark.']);
    exit;
}

if (!isset($_POST['research_id']) || !is_numeric($_POST['research_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid research ID.']);
    exit;
}


$student_id = $_SESSION['student_id'];
$paper_id = (int)$_POST['research_id'];

try {
    // Create table if not exists
    $conn->exec("CREATE TABLE IF NOT EXISTS bookmarks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        paper_id INT NOT NULL,
        bookmarked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_bookmark (student_id, paper_id)
    )");

    // Check if already bookmarked
    $stmt = $conn->prepare("SELECT id FROM bookmarks WHERE student_id = ? AND paper_id = ?");
    $stmt->execute([$student_id, $paper_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Already bookmarked.']);
        exit;
    }

    // Insert bookmark
    $stmt = $conn->prepare("INSERT INTO bookmarks (student_id, paper_id) VALUES (?, ?)");
    $stmt->execute([$student_id, $paper_id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
