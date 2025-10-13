<?php
session_start();
include 'db.php';
require_once __DIR__ . '/include/activity_log.php';

// Allow both admin and subadmin to access
$isAdmin = isset($_SESSION['admin_id']);
$isSubadmin = isset($_SESSION['subadmin_id']);
if (!$isAdmin && !$isSubadmin) {
    $_SESSION['error'] = "Unauthorized access.";
    header("Location: login.php");
    exit();
}

if (isset($_POST['research_id'])) {
    $research_id = $_POST['research_id'];

    try {
        // Set status to 2 = Archived
        $stmt = $conn->prepare("UPDATE research_submission SET status = 2 WHERE id = ?");
        $ok = $stmt->execute([$research_id]);
        // Also try to set is_archived = 1 if the column exists (best-effort)
        try {
            $conn->exec("UPDATE research_submission SET is_archived = 1 WHERE id = " . $conn->quote($research_id));
        } catch (Throwable $e) { /* ignore if column doesn't exist */ }

        if ($ok) {
            // Activity log
            try {
                $actorType = $isAdmin ? 'admin' : 'subadmin';
                $actorId = $isAdmin ? ($_SESSION['admin_id'] ?? null) : ($_SESSION['subadmin_id'] ?? null);
                log_activity($conn, $actorType, $actorId, 'archive_research', [
                    'research_id' => $research_id
                ]);
            } catch (Throwable $e) { /* ignore */ }
            $successMsg = "Research successfully archived.";
            $_SESSION['success'] = $successMsg;
        } else {
            $errMsg = "Failed to archive research.";
            $_SESSION['error'] = $errMsg;
        }
    } catch (PDOException $e) {
        $errMsg = "Database error: " . $e->getMessage();
        $_SESSION['error'] = $errMsg;
    }
} else {
    $_SESSION['error'] = "No research ID provided.";
}

// Respond JSON for AJAX requests
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
if (stripos($accept, 'application/json') !== false || isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $ok = empty($_SESSION['error']);
    echo json_encode([
        'ok' => $ok,
        'message' => $ok ? ($_SESSION['success'] ?? 'OK') : ($_SESSION['error'] ?? 'Error')
    ]);
    exit();
}

// Otherwise redirect back to the appropriate approvals page
$dest = $isAdmin ? 'research_approvals.php' : 'subadmin_research_approvals.php';
header("Location: $dest");
exit();
