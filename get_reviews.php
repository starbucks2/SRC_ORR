<?php
session_start();
include 'db.php';
header('Content-Type: application/json');

$research_id = filter_input(INPUT_GET, 'research_id', FILTER_VALIDATE_INT);

if (!$research_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid research ID.']);
    exit;
}

try {
    // Fetch reviews and join with students table to get reviewer's name
    $stmt = $conn->prepare("
        SELECT 
            r.rating, 
            r.comment, 
            r.created_at, 
            s.firstname, 
            s.lastname 
        FROM reviews r
        JOIN students s ON r.student_id = s.student_id
        WHERE r.research_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$research_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'reviews' => $reviews]);

} catch (PDOException $e) {
    // In a production environment, you would log this error instead of echoing it.
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
