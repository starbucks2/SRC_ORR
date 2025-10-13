<?php 
session_start();

// Only handle POST requests with an email
if (isset($_POST['email'])) {
    // Load database (PDO) and activity logger
    include_once __DIR__ . '/db.php';
    require_once __DIR__ . '/include/activity_log.php';


    $email = trim((string)$_POST['email']);

    // Determine redirect target based on role
    $redirect = isset($_SESSION['subadmin_id']) ? 'subadmin_verify_students.php' : 'verify_students.php';

    // Approve the student in the database using PDO prepared statement
    try {
        $stmt = $conn->prepare('UPDATE students SET is_verified = 1 WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->rowCount() === 0) {
            $_SESSION['error'] = 'No matching student found to approve.';
            header("Location: $redirect");
            exit();
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = 'Failed to approve student: ' . htmlspecialchars($e->getMessage());
        header("Location: $redirect");
        exit();
    }

    // Activity log (who approved whom)
    try {
        $actorType = isset($_SESSION['subadmin_id']) ? 'subadmin' : 'admin';
        $actorId = isset($_SESSION['subadmin_id']) ? $_SESSION['subadmin_id'] : ($_SESSION['admin_id'] ?? null);
        log_activity($conn, $actorType, $actorId, 'approve_student', [ 'email' => $email ]);
    } catch (Throwable $e) { /* fail silently */ }

    // Build absolute login URL
    $loginUrl = '';
    if (!empty($mailConfig['app_url'])) {
        $base = rtrim($mailConfig['app_url'], '/');
        $loginUrl = $base . '/login.php';
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? '/')), '/');
        $loginUrl = $scheme . '://' . $host . $basePath . '/login.php';
    }

    // Email notification disabled per user request
    $_SESSION['success'] = 'Student approved successfully.';

    header("Location: $redirect");
    exit();
}
?>