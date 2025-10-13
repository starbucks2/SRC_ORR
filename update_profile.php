<?php
session_start();
require 'db.php'; // Database connection

// Ensure user is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$current = null;
// Load current student row (used to preserve fields not present in form and to delete old photo)
try {
    $stCur = $conn->prepare("SELECT firstname, middlename, lastname, email, department, profile_pic FROM students WHERE student_id = ?");
    $stCur->execute([$student_id]);
    $current = $stCur->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) { $current = []; }
$firstname = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
$middlename = isset($_POST['middlename']) ? trim($_POST['middlename']) : '';
$lastname = isset($_POST['lastname']) ? trim($_POST['lastname']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$department = isset($_POST['department']) ? trim($_POST['department']) : ($current['department'] ?? '');
$profile_pic_name = $_FILES['profile_pic']['name'] ?? '';
$upload_dir = __DIR__ . '/images/';
$updated_profile_pic = '';

// Handle profile picture upload if provided
if (!empty($profile_pic_name) && isset($_FILES['profile_pic']) && is_uploaded_file($_FILES['profile_pic']['tmp_name'])) {
    // Ensure upload dir exists
    if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0755, true); }

    // Determine MIME type safely (fallbacks)
    $mime = null;
    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($_FILES['profile_pic']['tmp_name']);
    }
    if (!$mime && function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = @finfo_file($finfo, $_FILES['profile_pic']['tmp_name']);
            @finfo_close($finfo);
        }
    }
    if (!$mime) {
        $extGuess = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        $map = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        $mime = $map[$extGuess] ?? '';
    }

    $allowed_mime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (isset($allowed_mime[$mime])) {
        $ext = $allowed_mime[$mime];
        $base = pathinfo($_FILES['profile_pic']['name'], PATHINFO_FILENAME);
        $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', $base);
        $genName = 'student_' . $student_id . '_' . time() . '_' . mt_rand(1000,9999) . '_' . $safeBase . '.' . $ext;
        $dest = $upload_dir . $genName;
        if (@move_uploaded_file($_FILES['profile_pic']['tmp_name'], $dest)) {
            // Remove old profile picture if exists and is different
            $old = $current['profile_pic'] ?? '';
            if ($old && is_file($upload_dir . $old)) {
                @unlink($upload_dir . $old);
            }
            $updated_profile_pic = $genName; // only filename stored in DB/session
        }
    }
}

try {
    // Ensure schema has last_password_change column (run outside of transactions to avoid implicit commits)
    try {
        $conn->exec("ALTER TABLE students ADD COLUMN last_password_change DATETIME NULL AFTER password");
    } catch (Exception $e) { /* ignore if exists or not permitted */ }

    // Start transaction for the actual update operations
    $conn->beginTransaction();

    // Handle password change if provided
    if (!empty($_POST['current_password']) && !empty($_POST['new_password']) && !empty($_POST['confirm_password'])) {
        // Verify current password and get last change time
        $stmt = $conn->prepare("SELECT password, last_password_change FROM students WHERE student_id = ?");
        $stmt->execute([$student_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $current_hash = $row['password'] ?? '';
        $last_changed = $row['last_password_change'] ?? null;

        if (!password_verify($_POST['current_password'], $current_hash)) {
            throw new Exception("Current password is incorrect");
        }

        // Enforce 7-day cooldown (calculate remaining time until last_change + 7 days)
        if (!empty($last_changed)) {
            $targetTs = strtotime($last_changed . ' +6 days');
            if ($targetTs !== false) {
                $remaining = $targetTs - time();
                if ($remaining > 0) {
                    // Show remaining days, ceiling but cap at 7
                    $daysLeft = (int)ceil($remaining / 86400);
                    if ($daysLeft < 1) { $daysLeft = 1; }
                    if ($daysLeft > 7) { $daysLeft = 7; }
                    throw new Exception("You can change your password again after " . $daysLeft . " day(s).");
                }
            }
        }

        // Verify new passwords match
        if ($_POST['new_password'] !== $_POST['confirm_password']) {
            throw new Exception("New password and confirm password do not match.");
        }

        // Validate password strength (min 8 chars, uppercase, lowercase, number, special)
        $np = $_POST['new_password'];
        if (strlen($np) < 8
            || !preg_match('/[A-Z]/', $np)
            || !preg_match('/[a-z]/', $np)
            || !preg_match('/\d/', $np)
            || !preg_match('/[^A-Za-z0-9]/', $np)
        ) {
            throw new Exception("Password must be at least 8 characters and include uppercase, lowercase, number, and special character.");
        }

        // Hash new password
        $new_password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

        // Update password and last change timestamp
        $stmt = $conn->prepare("UPDATE students SET password = ?, last_password_change = NOW() WHERE student_id = ?");
        $stmt->execute([$new_password_hash, $student_id]);
    }


    // Always update firstname, middlename, lastname, email, department, and optionally profile_pic
    if ($updated_profile_pic) {
        $sql = "UPDATE students SET firstname = ?, middlename = ?, lastname = ?, email = ?, department = ?, profile_pic = ? WHERE student_id = ?";
        $params = [$firstname, $middlename, $lastname, $email, $department, $updated_profile_pic, $student_id];
        $_SESSION['profile_pic'] = $updated_profile_pic;
    } else {
        $sql = "UPDATE students SET firstname = ?, middlename = ?, lastname = ?, email = ?, department = ? WHERE student_id = ?";
        $params = [$firstname, $middlename, $lastname, $email, $department, $student_id];
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    // Update session values
    $_SESSION['firstname'] = $firstname;
    $_SESSION['middlename'] = $middlename;
    $_SESSION['lastname'] = $lastname;
    $_SESSION['email'] = $email;
    $_SESSION['department'] = $department;

    // Commit transaction if still active
    if ($conn->inTransaction()) { $conn->commit(); }
    
    $_SESSION['success'] = "Profile updated successfully";
    header("Location: student_dashboard.php");
    exit();

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) { $conn->rollBack(); }
    $_SESSION['error'] = "Error updating profile: " . $e->getMessage();
    header("Location: student_dashboard.php");
    exit();
}
?>