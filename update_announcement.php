<?php
session_start();
include 'db.php';

// Allow both admin and sub-admin with permission
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['subadmin_id'])) {
    header("Location: login.php");
    exit();
}

// Permission check for sub-admins
$can_manage_announcements = true;
if (isset($_SESSION['subadmin_id'])) {
    $can_manage_announcements = false;
    $permissions = json_decode($_SESSION['permissions'] ?? '[]', true);
    if (in_array('manage_announcements', $permissions)) {
        $can_manage_announcements = true;
    }
    if (!$can_manage_announcements) {
        $_SESSION['error'] = "You do not have permission to update announcements.";
        header("Location: subadmin_announcements.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $title = $_POST['title'];
    $open_at = $_POST['open_at'] ?? null;
    $deadline = $_POST['deadline'];
    $content = $_POST['content'];

    // Ensure open_at column exists
    $checkOpen = $conn->prepare("SHOW COLUMNS FROM announcements LIKE 'open_at'");
    $checkOpen->execute();
    if ($checkOpen->rowCount() == 0) {
        $conn->exec("ALTER TABLE announcements ADD COLUMN open_at DATETIME NULL AFTER content");
    }

    $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ?, open_at = ?, deadline = ? WHERE id = ?");
    $stmt->execute([$title, $content, $open_at, $deadline, $id]);

    $_SESSION['success'] = "Announcement updated successfully.";
    // Redirect to the correct page
    if (isset($_SESSION['admin_id'])) {
        header("Location: announcements.php");
    } else {
        header("Location: subadmin_announcements.php");
    }
    exit();
}
?>
