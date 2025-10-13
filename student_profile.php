<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

// Get user info
$student_id = $_SESSION['student_id'];
$firstname = $_SESSION['firstname'] ?? 'Student';
$middlename = $_SESSION['middlename'] ?? '';
$lastname = $_SESSION['lastname'] ?? 'Student';
$email = $_SESSION['email'] ?? 'Not Available';
$profile_pic = isset($_SESSION['profile_pic']) ? 'images/' . $_SESSION['profile_pic'] : 'images/default.jpg';
$department = $_SESSION['department'] ?? '';
$student_number = $_SESSION['student_number'] ?? '';

if (!file_exists($profile_pic) || empty($_SESSION['profile_pic'])) {
    $profile_pic = 'images/default.jpg';
}

// Fetch additional profile fields
$stmt = $conn->prepare("SELECT student_number FROM students WHERE student_id = ?");
$stmt->execute([$student_id]);
$user_data = $stmt->fetch();
// Prefer DB values when available
if (!empty($user_data['student_number'])) { $student_number = $user_data['student_number']; }

// Password change is always allowed (no 30-day restriction)
$can_change_password = true;
$days_remaining = 0;

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $new_firstname = trim($_POST['firstname']);
        $new_middlename = trim($_POST['middlename']);
        $new_lastname = trim($_POST['lastname']);
        
        if (!empty($new_firstname) && !empty($new_lastname)) {
            $stmt = $conn->prepare("UPDATE students SET firstname = ?, middlename = ?, lastname = ? WHERE student_id = ?");
            if ($stmt->execute([$new_firstname, $new_middlename, $new_lastname, $student_id])) {
                $_SESSION['firstname'] = $new_firstname;
                $_SESSION['middlename'] = $new_middlename;
                $_SESSION['lastname'] = $new_lastname;
                $firstname = $new_firstname;
                $middlename = $new_middlename;
                $lastname = $new_lastname;
                $message = 'Profile updated successfully!';
            } else {
                $error = 'Failed to update profile.';
            }
        } else {
            $error = 'First name and last name are required.';
        }
    }
    
    if (isset($_POST['change_password'])) {
        if (!$can_change_password) {
            $error = "You can only change your password once every 30 days. Please wait {$days_remaining} more days.";
        } else {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if ($new_password !== $confirm_password) {
                $error = 'New passwords do not match.';
            } elseif (strlen($new_password) < 6) {
                $error = 'Password must be at least 6 characters long.';
            } else {
                // Verify current password
                $stmt = $conn->prepare("SELECT password FROM students WHERE student_id = ?");
                $stmt->execute([$student_id]);
                $user = $stmt->fetch();
                
                if (password_verify($current_password, $user['password'])) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE students SET password = ?, last_password_change = NOW() WHERE student_id = ?");
                    if ($stmt->execute([$hashed_password, $student_id])) {
                        $message = 'Password changed successfully!';
                        $can_change_password = false;
                        $days_remaining = 30;
                    } else {
                        $error = 'Failed to change password.';
                    }
                } else {
                    $error = 'Current password is incorrect.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile | BNHS Research Repository</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
 <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'blue-primary': '#1e40af',
                        'blue-secondary': '#1e3a8a',
                        'gray-light': '#f3f4f6'
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50 min-h-screen flex flex-col lg:flex-row font-sans">
 <?php include 'student_sidebar.php'; ?>
<div class="flex-1 p-4 sm:p-6 lg:p-8">
    <!-- Messages -->
    <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 max-w-2xl mx-auto">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 max-w-2xl mx-auto">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Profile Section -->
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <!-- Profile Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
                <div class="flex items-center space-x-4">
                    <!-- Gmail-style circular profile picture -->
                    <div class="relative">
                        <img src="<?php echo htmlspecialchars($profile_pic); ?>" 
                             class="w-20 h-20 sm:w-16 sm:h-16 rounded-full object-cover border-2 border-gray-200 shadow-md" 
                             alt="Profile Picture">
                        <div class="absolute -bottom-1 -right-1 bg-green-500 w-4 h-4 rounded-full border-2 border-white"></div>
                    </div>
                    <div>
                        <h1 class="text-xl sm:text-2xl font-bold text-gray-900">
                            <?php echo htmlspecialchars($firstname . ' ' . $lastname); ?>
                        </h1>
                        <p class="text-gray-600 break-all"><?php echo htmlspecialchars($email); ?></p>
                        <p class="text-sm text-blue-600"><?php echo htmlspecialchars($department); ?></p>
                    </div>
                </div>
                
                <!-- Settings removed by request -->
            </div>
            
            <!-- Profile Details -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-gray-700 mb-2">Personal Information</h3>
                    <div class="space-y-2 text-sm">
                        <div><span class="font-medium">First Name:</span> <?php echo htmlspecialchars($firstname); ?></div>
                        <div><span class="font-medium">Middle Name:</span> <?php echo htmlspecialchars($middlename ?: 'N/A'); ?></div>
                        <div><span class="font-medium">Last Name:</span> <?php echo htmlspecialchars($lastname); ?></div>
                    </div>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-gray-700 mb-2">Academic Information</h3>
                    <div class="space-y-2 text-sm">
                        <?php if (!empty($student_number)): ?>
                        <div><span class="font-medium">Student Number:</span> <?php echo htmlspecialchars($student_number); ?></div>
                        <?php endif; ?>
                        <div><span class="font-medium">Department:</span> <?php echo htmlspecialchars($department); ?></div>
                        <div><span class="font-medium">Email:</span> <?php echo htmlspecialchars($email); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

</body>
</html>
