<?php
session_start();
require_once 'db.php';

// Check if user is admin
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['error'] = "Only administrators can manage strands.";
    header("Location: admin_dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update student strand
        if (isset($_POST['update_student'])) {
            $studentId = $_POST['student_id'];
            $strand = $_POST['student_strand'];
            
            // Verify student exists
            $stmt = $conn->prepare("SELECT id FROM students WHERE id = ?");
            $stmt->execute([$studentId]);
            if ($stmt->rowCount() > 0) {
                $stmt = $conn->prepare("UPDATE students SET strand = ? WHERE id = ?");
                $stmt->execute([$strand, $studentId]);
                $_SESSION['success'] = "Student strand updated successfully.";
            } else {
                $_SESSION['error'] = "Student not found.";
            }
        }
        
        // Update subadmin strand
        if (isset($_POST['update_subadmin'])) {
            $subadminId = $_POST['subadmin_id'];
            $strand = $_POST['subadmin_strand'];
            
            // Verify subadmin exists
            $stmt = $conn->prepare("SELECT id FROM subadmins WHERE id = ?");
            $stmt->execute([$subadminId]);
            if ($stmt->rowCount() > 0) {
                $stmt = $conn->prepare("UPDATE subadmins SET strand = ? WHERE id = ?");
                $stmt->execute([$strand, $subadminId]);
                $_SESSION['success'] = "Subadmin strand updated successfully.";
            } else {
                $_SESSION['error'] = "Subadmin not found.";
            }
        }
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating strand: " . $e->getMessage();
    }
}

header("Location: update_strands.php");
exit();
?>
