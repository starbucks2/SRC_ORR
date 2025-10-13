<?php
session_start();
include 'db.php';

// Check if a user is logged in (either admin or sub-admin)
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['subadmin_id'])) {
    $_SESSION['error'] = "You must be logged in to perform this action.";
    header("Location: login.php");
    exit();
}

// Check if we have a valid announcement ID
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    $_SESSION['error'] = "Invalid announcement ID.";
    header("Location: " . (isset($_SESSION['admin_id']) ? 'announcements.php' : 'subadmin_announcements.php'));
    exit();
}

try {
    // First, verify the announcement exists and get its details
    $stmt = $conn->prepare("SELECT * FROM announcements WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $announcement = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$announcement) {
        $_SESSION['error'] = "Announcement not found.";
        header("Location: " . (isset($_SESSION['admin_id']) ? 'announcements.php' : 'subadmin_announcements.php'));
        exit();
    }

    // If user is a subadmin, verify they can only delete their strand's announcements
    if (isset($_SESSION['subadmin_id'])) {
        // Get subadmin's strand
        $stmtStrand = $conn->prepare("SELECT strand FROM sub_admins WHERE id = ?");
        $stmtStrand->execute([$_SESSION['subadmin_id']]);
        $subadmin = $stmtStrand->fetch(PDO::FETCH_ASSOC);

        // Verify announcement belongs to their strand
        if ($announcement['strand'] !== $subadmin['strand']) {
            $_SESSION['error'] = "You can only delete announcements for your strand.";
            header("Location: subadmin_announcements.php");
            exit();
        }
    }

    // Delete the specific announcement
    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->execute([$_POST['id']]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "Announcement deleted successfully.";
    } else {
        $_SESSION['error'] = "No announcement was deleted.";
    }

} catch (PDOException $e) {
    $_SESSION['error'] = "Error deleting announcement: " . $e->getMessage();
}

// Redirect back to the appropriate page
header("Location: " . (isset($_SESSION['admin_id']) ? 'announcements.php' : 'subadmin_announcements.php'));
exit();
