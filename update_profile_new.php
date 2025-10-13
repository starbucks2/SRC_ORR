<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';

try {
    $student_id = $_SESSION['student_id'];
    $response = ['success' => false, 'message' => ''];

    // Basic information update
    if (isset($_POST['firstname']) && isset($_POST['lastname']) && isset($_POST['email'])) {
        $firstname = trim($_POST['firstname']);
        $middlename = trim($_POST['middlename'] ?? '');
        $lastname = trim($_POST['lastname']);
        $email = trim($_POST['email']);
        $strand = trim($_POST['strand'] ?? '');

        // Validate required fields
        if (empty($firstname) || empty($lastname) || empty($email)) {
            $_SESSION['error'] = "First name, last name, and email are required fields.";
            header("Location: student_dashboard.php");
            exit();
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = "Please enter a valid email address.";
            header("Location: student_dashboard.php");
            exit();
        }

        // Check if email already exists for another user
        $stmt = $conn->prepare("SELECT student_id FROM students WHERE email = ? AND student_id != ?");
        $stmt->execute([$email, $student_id]);
        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "This email is already registered to another account.";
            header("Location: student_dashboard.php");
            exit();
        }

        // Handle profile picture upload
        $profile_pic_path = $_SESSION['profile_pic'] ?? ''; // Keep existing picture by default
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_pic']['name'];
            $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            // Validate file type
            if (!in_array($filetype, $allowed)) {
                $_SESSION['error'] = "Only JPG, JPEG, PNG & GIF files are allowed.";
                header("Location: student_dashboard.php");
                exit();
            }

            // Generate unique filename
            $new_filename = uniqid() . "_" . basename($filename);
            $upload_path = "images/" . $new_filename;

            // Move uploaded file
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
                // Delete old profile picture if it exists and it's not the default image
                if (!empty($profile_pic_path) && file_exists("images/" . $profile_pic_path) && $profile_pic_path != 'default.jpg') {
                    unlink("images/" . $profile_pic_path);
                }
                $profile_pic_path = $new_filename;
            } else {
                $_SESSION['error'] = "Failed to upload profile picture.";
                header("Location: student_dashboard.php");
                exit();
            }
        }

        // Debug information
        error_log("Updating profile for student_id: " . $student_id);
        error_log("First Name: " . $firstname);
        error_log("Middle Name: " . $middlename);
        error_log("Last Name: " . $lastname);
        
        // Update basic information
        $stmt = $conn->prepare("UPDATE students SET 
            firstname = ?, 
            middlename = ?, 
            lastname = ?, 
            email = ?, 
            strand = ?,
            profile_pic = ?
            WHERE student_id = ?");

        if (!$stmt->execute([
            $firstname,
            $middlename,
            $lastname,
            $email,
            $strand,
            $profile_pic_path,
            $student_id
        ])) {
            error_log("Database error: " . implode(", ", $stmt->errorInfo()));
            throw new Exception("Failed to update profile information");
        }

        // Verify the update
        $verify = $conn->prepare("SELECT firstname, middlename, lastname FROM students WHERE student_id = ?");
        $verify->execute([$student_id]);
        $updated = $verify->fetch(PDO::FETCH_ASSOC);
        
        error_log("Updated values - First: {$updated['firstname']}, Middle: {$updated['middlename']}, Last: {$updated['lastname']}");

        // Update session variables with verified database values
        $_SESSION['firstname'] = $updated['firstname'];
        $_SESSION['middlename'] = $updated['middlename'];
        $_SESSION['lastname'] = $updated['lastname'];
        $_SESSION['email'] = $email;
        $_SESSION['strand'] = $strand;
        if ($profile_pic_path) {
            $_SESSION['profile_pic'] = $profile_pic_path;
        }
        
        error_log("Session updated - First: {$_SESSION['firstname']}, Middle: {$_SESSION['middlename']}, Last: {$_SESSION['lastname']}");

        $response['success'] = true;
        $response['message'] = "Profile updated successfully!";
    }

    // Password update
    if (!empty($_POST['current_password']) && !empty($_POST['new_password']) && !empty($_POST['confirm_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM students WHERE student_id = ?");
        $stmt->execute([$student_id]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current_password, $user['password'])) {
            $_SESSION['error'] = "Current password is incorrect.";
            header("Location: student_dashboard.php");
            exit();
        }

        // Validate new password
        if (strlen($new_password) < 8) {
            $_SESSION['error'] = "New password must be at least 8 characters long.";
            header("Location: student_dashboard.php");
            exit();
        }

        if ($new_password !== $confirm_password) {
            $_SESSION['error'] = "New passwords do not match.";
            header("Location: student_dashboard.php");
            exit();
        }

        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE students SET password = ? WHERE student_id = ?");
        $stmt->execute([$hashed_password, $student_id]);

        $response['success'] = true;
        $response['message'] = "Profile and password updated successfully!";
    }

    // Set success message if any update was successful
    if ($response['success']) {
        $_SESSION['success'] = $response['message'];
    }

} catch (PDOException $e) {
    $_SESSION['error'] = "An error occurred while updating your profile: " . $e->getMessage();
} catch (Exception $e) {
    $_SESSION['error'] = "An unexpected error occurred: " . $e->getMessage();
}

// Redirect back to dashboard
header("Location: student_dashboard.php");
exit();
