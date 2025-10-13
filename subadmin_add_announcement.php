<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['subadmin_id'])) {
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Prepare the SQL statement with placeholders
        $query = "INSERT INTO announcements (title, content, deadline) VALUES (:title, :content, :deadline)";
        $stmt = $conn->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(':title', $_POST['title']);
        $stmt->bindParam(':content', $_POST['content']);
        $stmt->bindParam(':deadline', $_POST['deadline']);
        
        // Execute the statement
        if ($stmt->execute()) {
            $_SESSION['success'] = "Announcement posted successfully!";
        } else {
            $_SESSION['error'] = "Error posting announcement.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error posting announcement: " . $e->getMessage();
    }
    
    header("Location: subadmin_announcements.php");
    exit();
}
?>