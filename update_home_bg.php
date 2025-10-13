<?php
session_start();
include 'db.php';

$uploadDir = 'uploads/backgrounds/';
$allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
$maxSize = 5 * 1024 * 1024; // 5MB

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['background_image'])) {
    $file = $_FILES['background_image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['bg_error'] = "Upload error.";
    } elseif (!in_array($file['type'], $allowedTypes)) {
        $_SESSION['bg_error'] = "Invalid file type.";
    } elseif ($file['size'] > $maxSize) {
        $_SESSION['bg_error'] = "File too large.";
    } else {
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'bg_' . time() . '.' . $ext;
        $filePath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // ✅ Update database
            try {
                $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'homepage_bg'");
                $stmt->execute([$filename]);

                $_SESSION['bg_success'] = "Background updated successfully!";
            } catch (Exception $e) {
                $_SESSION['bg_error'] = "Failed to save to database.";
            }
        } else {
            $_SESSION['bg_error'] = "Failed to save file.";
        }
    }

    header("Location: admin_dashboard.php");
    exit();
}
?>