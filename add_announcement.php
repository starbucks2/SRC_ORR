<?php
session_start();
include 'db.php';
require_once __DIR__ . '/include/activity_log.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['subadmin_id'])) {
    $_SESSION['error'] = "You must be logged in to perform this action.";
    header("Location: login.php");
    exit();
}

// Verify permissions
$can_manage_announcements = false;
if (isset($_SESSION['admin_id'])) {
    $can_manage_announcements = true;
} elseif (isset($_SESSION['subadmin_id'])) {
    $permissions = json_decode($_SESSION['permissions'] ?? '[]', true);
    if (in_array('manage_announcements', $permissions)) {
        $can_manage_announcements = true;
    }
}

if (!$can_manage_announcements) {
    $_SESSION['error'] = "You do not have permission to manage announcements.";
    header("Location: " . (isset($_SESSION['subadmin_id']) ? 'subadmin_dashboard.php' : 'admin_dashboard.php'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $open_at = $_POST['open_at'] ?? '';
        $deadline = $_POST['deadline'] ?? '';
        // Target audience: department (legacy 'strand' mapped to department)
        $department = $_POST['department'] ?? ($_POST['strand'] ?? null);

        // If user is a subadmin, force their department (fallback to legacy strand)
        if (isset($_SESSION['subadmin_id'])) {
            // Prefer session department if present
            if (!empty($_SESSION['department'])) {
                $department = $_SESSION['department'];
            } else {
                // Legacy: fetch strand from sub_admins table and treat as department
                try {
                    $stmt = $conn->prepare("SELECT strand FROM sub_admins WHERE id = ?");
                    $stmt->execute([$_SESSION['subadmin_id']]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!empty($result['strand'])) {
                        $department = $result['strand'];
                    }
                } catch (Throwable $e) { /* ignore */ }
            }
        }

        // Validate input
        if (empty($title) || empty($content) || empty($deadline) || empty($open_at)) {
            throw new Exception("All fields are required.");
        }

        // Ensure announcements.id behaves as AUTO_INCREMENT PRIMARY KEY to avoid strict mode errors on hosting
        try {
            // Check if column 'id' exists and whether it is auto_increment
            $colCheck = $conn->query("SHOW COLUMNS FROM announcements LIKE 'id'");
            $colInfo = $colCheck ? $colCheck->fetch(PDO::FETCH_ASSOC) : null;
            if (!$colInfo) {
                // Add 'id' column if missing
                $conn->exec("ALTER TABLE announcements ADD COLUMN id INT UNSIGNED NOT NULL FIRST");
            }
            // Refresh column info
            $colCheck = $conn->query("SHOW COLUMNS FROM announcements LIKE 'id'");
            $colInfo = $colCheck ? $colCheck->fetch(PDO::FETCH_ASSOC) : null;
            $isAuto = isset($colInfo['Extra']) && stripos($colInfo['Extra'], 'auto_increment') !== false;
            // Ensure primary key exists on 'id'
            $pkCheck = $conn->query("SHOW KEYS FROM announcements WHERE Key_name = 'PRIMARY'");
            $pkInfo = $pkCheck ? $pkCheck->fetch(PDO::FETCH_ASSOC) : null;
            $hasPkOnId = $pkInfo && isset($pkInfo['Column_name']) && strtolower($pkInfo['Column_name']) === 'id';

            if (!$isAuto || !$hasPkOnId) {
                // Modify column to be AUTO_INCREMENT and set as PRIMARY KEY
                // Some MySQL versions require dropping existing PK first if it's on a different column
                if ($pkInfo && (!$hasPkOnId)) {
                    $conn->exec("ALTER TABLE announcements DROP PRIMARY KEY");
                }
                $conn->exec("ALTER TABLE announcements MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id)");
            }
        } catch (Throwable $schemaEx) {
            // If this fails, continue; insertion may still work on non-strict systems
        }

        // Ensure open_at column exists
        $checkOpen = $conn->prepare("SHOW COLUMNS FROM announcements LIKE 'open_at'");
        $checkOpen->execute();
        if ($checkOpen->rowCount() == 0) {
            $conn->exec("ALTER TABLE announcements ADD COLUMN open_at DATETIME NULL AFTER content");
        }

        // Ensure department column exists (target audience)
        try {
            $checkDeptCol = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements' AND COLUMN_NAME = 'department'");
            $checkDeptCol->execute();
            if ((int)$checkDeptCol->fetchColumn() === 0) {
                $conn->exec("ALTER TABLE announcements ADD COLUMN department VARCHAR(100) NULL AFTER open_at");
            }
        } catch (Throwable $e) { /* ignore */ }

        // Prepare the SQL statement with placeholders (use department)
        $query = "INSERT INTO announcements (title, content, open_at, deadline, department, created_at) VALUES (:title, :content, :open_at, :deadline, :department, NOW())";
        $stmt = $conn->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':content', $content);
        $stmt->bindParam(':open_at', $open_at);
        $stmt->bindParam(':deadline', $deadline);
        $stmt->bindParam(':department', $department);
        
        // Execute the statement
        if ($stmt->execute()) {
            // Activity log
            try {
                $actorType = isset($_SESSION['subadmin_id']) ? 'subadmin' : 'admin';
                $actorId = isset($_SESSION['subadmin_id']) ? $_SESSION['subadmin_id'] : ($_SESSION['admin_id'] ?? null);
                log_activity($conn, $actorType, $actorId, 'post_announcement', [
                    'title' => $title,
                    'department' => $department,
                    'open_at' => $open_at,
                    'deadline' => $deadline
                ]);
            } catch (Throwable $e) { /* ignore */ }
            $_SESSION['success'] = "Announcement posted successfully!";
        } else {
            $_SESSION['error'] = "Error posting announcement.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error posting announcement: " . $e->getMessage();
    }
    
    // Redirect back to the appropriate announcements page
    $redirect = isset($_SESSION['subadmin_id']) ? 'subadmin_announcements.php' : 'announcements.php';
    header("Location: $redirect");
    exit();
}
?>