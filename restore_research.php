<?php
session_start();
include 'db.php';

// Allow both admin and subadmin to restore (same as archived_research.php access)
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['subadmin_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $ok = false;
    try {
        // Set status back to pending (0)
        $stmt = $conn->prepare("UPDATE research_submission SET status = 0 WHERE id = ?");
        $ok = $stmt->execute([$id]);
        // Best-effort clear is_archived if column exists
        try { $conn->exec("UPDATE research_submission SET is_archived = 0 WHERE id = " . $conn->quote($id)); } catch (Throwable $e) { /* ignore */ }
    } catch (Throwable $e) { $ok = false; }

    $_SESSION['bg_success'] = $ok ? "Research paper restored successfully." : "Failed to restore paper.";
}

header("Location: archived_research.php");
exit();
