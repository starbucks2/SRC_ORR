<?php
session_start();
include 'db.php';
require_once __DIR__ . '/include/activity_log.php';

// Try to raise upload limits at runtime (some hosts allow this)
@ini_set('upload_max_filesize', '1024M');
@ini_set('post_max_size', '1024M');
@ini_set('memory_limit', '1536M');
@ini_set('max_execution_time', '900');
@ini_set('max_input_time', '900');
@ini_set('file_uploads', '1');

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    $_SESSION['error'] = "Unauthorized access.";
    header("Location: login.php");
    exit();
}

// Role-based restriction removed: all students may upload anytime (no announcement requirement)

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_POST['student_id'];
    $title = trim($_POST['title']);
    $year = $_POST['year'];
    $abstract = trim($_POST['abstract']);
    $members = trim($_POST['members']);
    $keywords = trim($_POST['keywords'] ?? '');
    // Department targeting (replaces legacy 'strand')
    $department = $_POST['department'] ?? ($_SESSION['department'] ?? '');
    // Course/Strand
    $course_strand = $_POST['course_strand'] ?? ($_SESSION['course_strand'] ?? '');
    // Derive student_id and course_strand from the database for this student
    $student_id = null;
    try {
        $gstmt = $conn->prepare("SELECT student_id, course_strand FROM students WHERE student_id = ?");
        $gstmt->execute([$student_id]);
        $grow = $gstmt->fetch(PDO::FETCH_ASSOC);
        if ($grow && isset($grow['student_id'])) {
            $student_id = (string)$grow['student_id'];
        }
        if ($grow && isset($grow['course_strand']) && empty($course_strand)) {
            $course_strand = (string)$grow['course_strand'];
        }
        // group_number removed from upload flow
    } catch (Exception $e) { /* ignore; allow null if not found */ }
    // Section removed from the model
    $status = 0; // Pending

    // Validate required fields
    if (empty($title) || empty($abstract) || empty($members)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: student_dashboard.php");
        exit();
    }

    // --- Handle PDF Document Upload ---
    $document = '';
    if (!empty($_FILES['document']['name'])) {
        $docName = $_FILES['document']['name'];
        $docTmp = $_FILES['document']['tmp_name'];
        $docSize = $_FILES['document']['size'];
        $docExt = strtolower(pathinfo($docName, PATHINFO_EXTENSION));
        $allowedDocs = ['pdf'];

        if (!in_array($docExt, $allowedDocs)) {
            $_SESSION['error'] = "Invalid document type. Only PDF files are allowed.";
            header("Location: student_dashboard.php");
            exit();
        }

        // Do not enforce an application-level size limit. Server limits (upload_max_filesize/post_max_size) may still apply.

        $document = 'uploads/research_documents/' . uniqid('doc_') . '.' . $docExt;
        $uploadDir = 'uploads/research_documents';
        // Ensure directory exists and is writable
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0777, true)) {
                $_SESSION['error'] = "Upload failed: cannot create uploads/research_documents directory (permissions).";
                header("Location: student_dashboard.php");
                exit();
            }
        }
        if (!is_writable($uploadDir)) {
            @chmod($uploadDir, 0777);
        }

        // Surface native PHP upload errors clearly
        $upErr = $_FILES['document']['error'] ?? UPLOAD_ERR_OK;
        if ($upErr !== UPLOAD_ERR_OK) {
            $map = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds server upload_max_filesize.',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE.',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
            ];
            $msg = $map[$upErr] ?? 'Unknown upload error.';
            // If ini limits are the cause, show the current limits
            if ($upErr === UPLOAD_ERR_INI_SIZE || $upErr === UPLOAD_ERR_FORM_SIZE) {
                $um = ini_get('upload_max_filesize');
                $pm = ini_get('post_max_size');
                $msg .= " (Server limits: upload_max_filesize=$um, post_max_size=$pm)";
            }
            $_SESSION['error'] = 'Upload failed: ' . $msg;
            header("Location: student_dashboard.php");
            exit();
        }

        if (!is_uploaded_file($docTmp)) {
            $_SESSION['error'] = "Upload failed: temporary file missing (is_uploaded_file check).";
            header("Location: student_dashboard.php");
            exit();
        }

        if (!@move_uploaded_file($docTmp, $document)) {
            $_SESSION['error'] = "Failed to move uploaded file into uploads/research_documents. Please check folder permissions.";
            header("Location: student_dashboard.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Research document (PDF) is required.";
        header("Location: student_dashboard.php");
        exit();
    }

    // --- Handle Image Upload (Optional) ---
    $image = '';
    if (!empty($_FILES['image']['name'])) {
        $imgErr = $_FILES['image']['error'] ?? UPLOAD_ERR_OK;
        if ($imgErr !== UPLOAD_ERR_OK) {
            $msg = 'Image upload error.';
            if ($imgErr === UPLOAD_ERR_INI_SIZE) $msg = 'Image exceeds server upload_max_filesize limit.';
            elseif ($imgErr === UPLOAD_ERR_FORM_SIZE) $msg = 'Image exceeds the form MAX_FILE_SIZE limit.';
            elseif ($imgErr === UPLOAD_ERR_PARTIAL) $msg = 'Image was only partially uploaded.';
            elseif ($imgErr === UPLOAD_ERR_NO_FILE) $msg = 'No image file was uploaded.';
            elseif ($imgErr === UPLOAD_ERR_NO_TMP_DIR) $msg = 'Missing temporary folder on server.';
            elseif ($imgErr === UPLOAD_ERR_CANT_WRITE) $msg = 'Failed to write image to disk.';
            elseif ($imgErr === UPLOAD_ERR_EXTENSION) $msg = 'A PHP extension stopped the image upload.';
            $_SESSION['error'] = $msg;
            header("Location: student_dashboard.php");
            exit();
        }

        $imageName = $_FILES['image']['name'];
        $imageTmp = $_FILES['image']['tmp_name'];
        $imageSize = $_FILES['image']['size'];
        $imageExt = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
        $allowedImages = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($imageExt, $allowedImages)) {
            $_SESSION['error'] = "Invalid image format. Only JPG, PNG, GIF allowed.";
            header("Location: student_dashboard.php");
            exit();
        }

        if ($imageSize > 10 * 1024 * 1024) { // 10MB limit
            $_SESSION['error'] = "Image too large. Maximum 10MB allowed.";
            header("Location: student_dashboard.php");
            exit();
        }

        $targetDir = 'uploads/research_images';
        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
                $_SESSION['error'] = "Failed to create image upload directory.";
                header("Location: student_dashboard.php");
                exit();
            }
        }
        if (!is_uploaded_file($imageTmp)) {
            $_SESSION['error'] = "Invalid image upload (temp file missing).";
            header("Location: student_dashboard.php");
            exit();
        }

        $image = $targetDir . '/' . uniqid('img_') . '.' . $imageExt;
        if (!move_uploaded_file($imageTmp, $image)) {
            $_SESSION['error'] = "Failed to upload image.";
            header("Location: student_dashboard.php");
            exit();
        }
    }

    // Ensure schema has necessary columns
    try {
        $chk = $conn->prepare("SHOW COLUMNS FROM research_submission LIKE 'group_number'");
        $chk->execute();
        // Ensure department column exists (replace legacy 'strand')
        $chkDept = $conn->prepare("SHOW COLUMNS FROM research_submission LIKE 'department'");
        $chkDept->execute();
        if ($chkDept->rowCount() == 0) {
            $conn->exec("ALTER TABLE research_submission ADD COLUMN department VARCHAR(100) NULL AFTER members");
        }
        // Ensure student_id column exists for easier tracking
        $chkStudNo = $conn->prepare("SHOW COLUMNS FROM research_submission LIKE 'student_id'");
        $chkStudNo->execute();
        if ($chkStudNo->rowCount() == 0) {
            $conn->exec("ALTER TABLE research_submission ADD COLUMN student_id VARCHAR(50) NULL AFTER student_id");
        }
        // Ensure keywords column exists
        $chkKw = $conn->prepare("SHOW COLUMNS FROM research_submission LIKE 'keywords'");
        $chkKw->execute();
        if ($chkKw->rowCount() == 0) {
            $conn->exec("ALTER TABLE research_submission ADD COLUMN keywords VARCHAR(255) NULL AFTER abstract");
        }
        // Ensure course_strand column exists
        $chkCs = $conn->prepare("SHOW COLUMNS FROM research_submission LIKE 'course_strand'");
        $chkCs->execute();
        if ($chkCs->rowCount() == 0) {
            $conn->exec("ALTER TABLE research_submission ADD COLUMN course_strand VARCHAR(50) NULL AFTER department");
        }
        // If legacy 'strand' exists but 'department' is missing, above adds department; we won't write to strand anymore
        // Ensure 'year' column can store Academic Year strings e.g., 'S.Y. 2025-2026'
        $ctype = $conn->prepare("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'research_submission' AND COLUMN_NAME = 'year'");
        $ctype->execute();
        $dt = strtolower((string)$ctype->fetchColumn());
        if (in_array($dt, ['int','integer','tinyint','smallint','mediumint','bigint'])) {
            $conn->exec("ALTER TABLE research_submission MODIFY COLUMN year VARCHAR(32) NOT NULL");
        }
    } catch (Exception $e) { /* ignore schema adjustments */ }

    // --- Insert into Database ---
    try {
        $stmt = $conn->prepare("INSERT INTO research_submission 
            (student_id, student_id, title, year, abstract, keywords, members, department, course_strand, document, image, status, submission_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$student_id, $student_id, $title, $year, $abstract, $keywords, $members, $department, $course_strand, $document, $image, $status]);

        // Activity log
        try {
            log_activity($conn, 'student', $_SESSION['student_id'] ?? null, 'upload_research', [
                'title' => $title,
                'student_id' => $student_id,
                'department' => $department,
                'course_strand' => $course_strand,
                'year' => $year,
                'document' => $document
            ]);
        } catch (Throwable $e) { /* ignore */ }

        $_SESSION['success'] = "Research submitted successfully! Awaiting approval.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Upload failed: " . $e->getMessage();
    }

    header("Location: student_dashboard.php");
    exit();

} else {
    // Invalid request method
    http_response_code(405);
    echo "Method not allowed.";
    exit();
}
?>