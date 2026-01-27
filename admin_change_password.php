<?php
session_start();
include 'db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['error'] = "You must be logged in as an admin.";
    header("Location: admin_login.php");
    exit();
}

$success_message = $error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    try {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM admin WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($current_password, $admin['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 8) {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $_SESSION['admin_id']]);
                    
                    $success_message = "Password successfully updated!";
                } else {
                    $error_message = "New password must be at least 8 characters long.";
                }
            } else {
                $error_message = "New passwords do not match.";
            }
        } else {
            $error_message = "Current password is incorrect.";
        }
    } catch (PDOException $e) {
        $error_message = "Error updating password: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex flex-col md:flex-row">
    <?php include 'admin_sidebar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 md:p-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-2xl shadow-lg p-6 md:p-8 mb-6">
                <h1 class="text-2xl font-bold text-blue-900 mb-6 flex items-center">
                    <i class="fas fa-key mr-3"></i> Change Password
                </h1>

                <?php if ($success_message): ?>
                    <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                        <div class="relative">
                            <input type="password" id="current_password" name="current_password" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 pr-10">
                            <button type="button" onclick="togglePassword('current_password')" 
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-800">
                                <i class="fas fa-eye" id="current_password_icon"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                        <div class="relative">
                            <input type="password" id="new_password" name="new_password" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 pr-10">
                            <button type="button" onclick="togglePassword('new_password')"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-800">
                                <i class="fas fa-eye" id="new_password_icon"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                        <div class="relative">
                            <input type="password" id="confirm_password" name="confirm_password" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 pr-10">
                            <button type="button" onclick="togglePassword('confirm_password')"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-800">
                                <i class="fas fa-eye" id="confirm_password_icon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-4">
                        <a href="admin_dashboard.php" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            Cancel
                        </a>
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(inputId + '_icon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
