<?php
session_start();
include 'db.php'; // Database connection
require_once __DIR__ . '/include/activity_log.php';

// Authorization: Admins allowed. Sub-admins allowed if they have verify_students permission
$is_admin = isset($_SESSION['admin_id']);
$is_subadmin = isset($_SESSION['subadmin_id']);
$authorized = $is_admin;
if (!$authorized && $is_subadmin) {
    $strand = strtolower($_SESSION['strand'] ?? '');
    $permsRaw = $_SESSION['permissions'] ?? [];
    $permissions = is_array($permsRaw) ? $permsRaw : (json_decode($permsRaw, true) ?: []);
    $authorized = in_array('verify_students', $permissions, true) || ($strand && in_array('verify_students_' . $strand, $permissions, true));
}
if (!$authorized) {
    $_SESSION['error'] = "Unauthorized access.";
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $student_id = (int)$_POST['student_id'];

    try {
        // Delete the student record (correct PK is student_id)
        $stmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
        $stmt->execute([$student_id]);

        // Activity log
        try {
            $actorType = $is_admin ? 'admin' : 'subadmin';
            $actorId = $is_admin ? ($_SESSION['admin_id'] ?? null) : ($_SESSION['subadmin_id'] ?? null);
            log_activity($conn, $actorType, $actorId, 'reject_student', [
                'student_id' => $student_id
            ]);
        } catch (Throwable $e) { /* ignore */ }

        $_SESSION['success'] = "Student rejected and removed successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to reject student: " . $e->getMessage();
    }
}

// Redirect back to referring page if possible, else go to appropriate verify page
$ref = $_SERVER['HTTP_REFERER'] ?? '';
if ($ref) {
    header("Location: " . $ref);
} else {
    header("Location: " . ($is_admin ? 'verify_students.php' : 'subadmin_verify_students.php'));
}
exit();
?>
