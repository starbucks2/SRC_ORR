<?php
session_start();
include 'db.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: subadmin_dashboard.php");
    exit();
}

// Determine if an admin is performing the update
$is_admin = isset($_SESSION['admin_id']);

// Target sub-admin id: if admin provides a target_id, use it; otherwise use logged-in sub-admin
$target_id = null;
if ($is_admin && !empty($_POST['target_id'])) {
    $target_id = (int)$_POST['target_id'];
} elseif (isset($_SESSION['subadmin_id'])) {
    $target_id = (int)$_SESSION['subadmin_id'];
}

if (!$target_id) {
    $_SESSION['error'] = "No sub-admin selected for update.";
    header('Location: ' . ($is_admin ? 'manage_subadmins.php' : 'subadmin_dashboard.php'));
    exit();
}


// Common fields
$fullname = trim($_POST['fullname'] ?? '');
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$current_password = $_POST['current_password'] ?? '';
// Profile picture handling vars
$newProfilePicName = null; // null means no change; string means set to new; special value false means remove
$oldProfilePic = null;

if ($fullname === '') {
    $_SESSION['error'] = 'Full name is required.';
    header('Location: ' . ($is_admin ? 'manage_subadmins.php' : 'subadmin_dashboard.php'));
    exit();
}

try {
    // Fetch target sub-admin record
    $stmt = $conn->prepare("SELECT * FROM sub_admins WHERE id = ?");
    $stmt->execute([$target_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['error'] = 'Sub-admin account not found.';
        header('Location: ' . ($is_admin ? 'manage_subadmins.php' : 'subadmin_dashboard.php'));
        exit();
    }

    $changePassword = false;
    $new_hashed = null;

    // Ensure required columns exist (best-effort on hosting where schema may vary)
    // permissions column
    try {
        $chkPerm = $conn->prepare("SHOW COLUMNS FROM sub_admins LIKE 'permissions'");
        $chkPerm->execute();
        if ($chkPerm->rowCount() == 0) {
            // Default to TEXT to store JSON
            $conn->exec("ALTER TABLE sub_admins ADD COLUMN permissions TEXT NULL AFTER email");
        }
    } catch (Exception $e) { /* ignore */ }

    // strand column
    try {
        $chkStrand = $conn->prepare("SHOW COLUMNS FROM sub_admins LIKE 'strand'");
        $chkStrand->execute();
        if ($chkStrand->rowCount() == 0) {
            $conn->exec("ALTER TABLE sub_admins ADD COLUMN strand VARCHAR(50) NULL AFTER permissions");
        }
    } catch (Exception $e) { /* ignore */ }

    // profile_pic column
    $hasProfilePicCol = false;
    try {
        $chk = $conn->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sub_admins' AND COLUMN_NAME = 'profile_pic'");
        $chk->execute();
        $hasProfilePicCol = (bool)$chk->fetchColumn();
    } catch (Exception $e) {
        $hasProfilePicCol = false;
    }
    if (!$hasProfilePicCol) {
        try {
            $conn->exec("ALTER TABLE sub_admins ADD COLUMN profile_pic VARCHAR(255) NULL AFTER strand");
            $hasProfilePicCol = true;
        } catch (Exception $e) {
            $hasProfilePicCol = false;
        }
    }

    // Prepare current pic
    $oldProfilePic = $user['profile_pic'] ?? null;

    // Handle remove checkbox
    if (isset($_POST['remove_profile_pic']) && $_POST['remove_profile_pic'] == '1') {
        $newProfilePicName = false; // mark for removal
    }

    // Handle upload if provided
    if (isset($_FILES['profile_pic']) && is_array($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            // Robust MIME detection with fallbacks (some hosts lack mime_content_type)
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
                // Fallback by extension
                $extGuess = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
                $map = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
                $mime = $map[$extGuess] ?? '';
            }
            if (isset($allowed[$mime])) {
                $ext = $allowed[$mime];
                $base = pathinfo($_FILES['profile_pic']['name'], PATHINFO_FILENAME);
                $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', $base);
                $gen = 'subadmin_' . time() . '_' . mt_rand(1000,9999) . '_' . $safeBase . '.' . $ext;
                $dest = __DIR__ . '/images/' . $gen;
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $dest)) {
                    $newProfilePicName = $gen; // set to new
                } else {
                    $_SESSION['error'] = 'Failed to save uploaded profile picture.';
                    header('Location: ' . ($is_admin ? 'manage_subadmins.php' : 'subadmin_dashboard.php'));
                    exit();
                }
            } else {
                $_SESSION['error'] = 'Unsupported profile picture format. Please upload JPG, PNG, or WEBP.';
                header('Location: ' . ($is_admin ? 'manage_subadmins.php' : 'subadmin_dashboard.php'));
                exit();
            }
        } else {
            $_SESSION['error'] = 'Error uploading profile picture.';
            header('Location: ' . ($is_admin ? 'manage_subadmins.php' : 'subadmin_dashboard.php'));
            exit();
        }
    }

    if ($is_admin) {
        // Admin editing another sub-admin: allow updating email, permissions, and password without current password
        $email = trim($_POST['email'] ?? $user['email']);
        $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : null; // may be null => leave unchanged
        // Determine strand: if provided in POST, use it; otherwise keep existing
        $strand = isset($_POST['strand']) ? trim($_POST['strand']) : ($user['strand'] ?? null);

        // Basic email validation
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'A valid email is required.';
            header('Location: manage_subadmins.php');
            exit();
        }

        // Email uniqueness check
        $check = $conn->prepare("SELECT id FROM sub_admins WHERE email = ? AND id != ? LIMIT 1");
        $check->execute([$email, $target_id]);
        if ($check->rowCount() > 0) {
            $_SESSION['error'] = 'Email already in use by another account.';
            header('Location: manage_subadmins.php');
            exit();
        }

        // If admin provided a new password, validate
        if ($new_password !== '' || $confirm_password !== '') {
            if ($new_password === '' || $confirm_password === '') {
                $_SESSION['error'] = 'Please provide the new password and confirmation.';
                header('Location: manage_subadmins.php');
                exit();
            }
            if ($new_password !== $confirm_password) {
                $_SESSION['error'] = 'New passwords do not match.';
                header('Location: manage_subadmins.php');
                exit();
            }
            if (strlen($new_password) < 8) {
                $_SESSION['error'] = 'New password must be at least 8 characters long.';
                header('Location: manage_subadmins.php');
                exit();
            }
            $changePassword = true;
            $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
        }

        // Build update query (include strand and profile_pic if column exists)
        $params = [$fullname, $email, $permissions !== null ? json_encode($permissions) : $user['permissions']];
        $sql = "UPDATE sub_admins SET fullname = ?, email = ?, permissions = ?";
        if ($changePassword) {
            $sql .= ", password = ?";
            $params[] = $new_hashed;
        }
        if ($strand !== null) {
            $sql .= ", strand = ?";
            $params[] = $strand;
        }
        if ($hasProfilePicCol) {
            if ($newProfilePicName === false) {
                $sql .= ", profile_pic = NULL";
            } elseif (is_string($newProfilePicName)) {
                $sql .= ", profile_pic = ?";
                $params[] = $newProfilePicName;
            }
        }
        $sql .= " WHERE id = ?";
        $params[] = $target_id;
        $stmt = $conn->prepare($sql);
        $ok = $stmt->execute($params);

        if ($ok) {
            // Remove old file if replaced or removed
            if ($hasProfilePicCol && $oldProfilePic && ($newProfilePicName === false || is_string($newProfilePicName))) {
                $oldPath = __DIR__ . '/images/' . $oldProfilePic;
                if (is_file($oldPath)) { @unlink($oldPath); }
            }
            if ($changePassword) {
                $_SESSION['success'] = 'Password changed successfully.';
            } else {
                $_SESSION['success'] = 'Sub-admin updated successfully.';
            }
            header('Location: manage_subadmins.php');
            exit();
        } else {
            $_SESSION['error'] = 'No changes were saved.';
            header('Location: manage_subadmins.php');
            exit();
        }
    } else {
        // Sub-admin editing their own profile: require current password to change password, only fullname and password allowed
        if ($current_password !== '' || $new_password !== '' || $confirm_password !== '') {
            // Current password required
            if ($current_password === '') {
                $_SESSION['error'] = 'Current password is required to change your password.';
                header('Location: subadmin_dashboard.php');
                exit();
            }

            // Verify current password
            if (!password_verify($current_password, $user['password'])) {
                $_SESSION['error'] = 'Current password is incorrect.';
                header('Location: subadmin_dashboard.php');
                exit();
            }

            // Validate new password fields
            if ($new_password === '' || $confirm_password === '') {
                $_SESSION['error'] = 'Please provide the new password and confirmation.';
                header('Location: subadmin_dashboard.php');
                exit();
            }

            if ($new_password !== $confirm_password) {
                $_SESSION['error'] = 'New passwords do not match.';
                header('Location: subadmin_dashboard.php');
                exit();
            }

            if (strlen($new_password) < 8) {
                $_SESSION['error'] = 'New password must be at least 8 characters long.';
                header('Location: subadmin_dashboard.php');
                exit();
            }

            $changePassword = true;
            $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
        }

        // Perform update for self (allow profile_pic change and keep strand unchanged here)
        $params = [$fullname];
        $sql = "UPDATE sub_admins SET fullname = ?";
        if ($changePassword) {
            $sql .= ", password = ?";
            $params[] = $new_hashed;
        }
        if ($hasProfilePicCol) {
            if ($newProfilePicName === false) {
                $sql .= ", profile_pic = NULL";
            } elseif (is_string($newProfilePicName)) {
                $sql .= ", profile_pic = ?";
                $params[] = $newProfilePicName;
            }
        }
        $sql .= " WHERE id = ?";
        $params[] = $target_id;
        $stmt = $conn->prepare($sql);
        $ok = $stmt->execute($params);

        if ($ok) {
            // Remove old file if replaced or removed
            if ($hasProfilePicCol && $oldProfilePic && ($newProfilePicName === false || is_string($newProfilePicName))) {
                $oldPath = __DIR__ . '/images/' . $oldProfilePic;
                if (is_file($oldPath)) { @unlink($oldPath); }
            }
            // Refresh session fullname from DB if this is the logged-in sub-admin
            if (isset($_SESSION['subadmin_id']) && $_SESSION['subadmin_id'] == $target_id) {
                $stmt = $conn->prepare("SELECT fullname FROM sub_admins WHERE id = ?");
                $stmt->execute([$target_id]);
                $updated = $stmt->fetch(PDO::FETCH_ASSOC);
                $_SESSION['fullname'] = $updated['fullname'] ?? $fullname;
            }
            if ($changePassword) {
                $_SESSION['success'] = 'Password changed successfully.';
            } else {
                $_SESSION['success'] = 'Profile updated successfully.';
            }
            header('Location: subadmin_dashboard.php');
            exit();
        } else {
            $_SESSION['error'] = 'No changes were saved.';
            header('Location: subadmin_dashboard.php');
            exit();
        }
    }
} catch (PDOException $e) {
    error_log('update_subadmin.php error: ' . $e->getMessage());
    $_SESSION['error'] = 'Failed to update profile: ' . $e->getMessage();
    header('Location: ' . ($is_admin ? 'manage_subadmins.php' : 'subadmin_dashboard.php'));
    exit();
}
