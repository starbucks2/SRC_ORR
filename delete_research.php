<?php
session_start();
include 'db.php'; 

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    $_SESSION['error'] = "Unauthorized access!";
    header("Location: login.php");
    exit();
}

// Check if we have a research ID from the URL parameter (GET request)
if (isset($_GET['id'])) {
    $student_id = $_SESSION['student_id'];
    $research_id = $_GET['id'];

    try {
        // Fetch the document path before deleting
        $stmt = $conn->prepare("SELECT document, image FROM research_submission WHERE id = ? AND student_id = ?");
        $stmt->execute([$research_id, $student_id]);
        $research = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($research) {
            // Define file paths
            $documentPath = 'uploads/' . $research['document'];
            $imagePath = 'uploads/' . $research['image'];

            // Delete from database first
            $stmt = $conn->prepare("DELETE FROM research_submission WHERE id = ? AND student_id = ?");
            $stmt->execute([$research_id, $student_id]);

            // Delete files from server if they exist
            if (file_exists($documentPath)) {
                if (!unlink($documentPath)) {
                    error_log("Failed to delete document file: " . $documentPath);
                }
            }
            
            if (file_exists($imagePath)) {
                if (!unlink($imagePath)) {
                    error_log("Failed to delete image file: " . $imagePath);
                }
            }

            $_SESSION['success'] = "Research deleted successfully!";
        } else {
            $_SESSION['error'] = "Research not found or you don't have permission to delete it!";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Deletion failed: " . $e->getMessage();
        error_log("Database error in delete_research.php: " . $e->getMessage());
    }
} else {
    $_SESSION['error'] = "No research ID specified!";
}

header("Location: student_dashboard.php");
exit();
?>