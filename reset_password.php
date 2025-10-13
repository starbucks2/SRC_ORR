<?php
session_start();
include 'db.php';

$error = '';
$success = '';
$user = null;              // fetched user record
$userRole = null;          // 'student' | 'subadmin'
$displayName = null;       // for UI greeting
$token = $_GET['token'] ?? '';

// Function to decrypt and verify token
function verifyToken($encryptedToken) {
    // Same secret key as in forgot_password_ajax.php
    $secretKey = 'MySecureK3y2024_Bnhs_P@sswordR3set!_ChangeTh1sInProduction';
    
    try {
        $decoded = base64_decode($encryptedToken);
        $parts = explode('|', $decoded);
        
        if (count($parts) !== 2) {
            return false;
        }
        
        $token = $parts[0];
        $hash = $parts[1];
        
        // Verify hash
        if (!hash_equals(hash_hmac('sha256', $token, $secretKey), $hash)) {
            return false;
        }
        
        // Decode token data
        $tokenData = json_decode(base64_decode($token), true);
        
        if (!$tokenData) {
            return false;
        }
        
        // Check if token has expired
        if (time() > $tokenData['expiry']) {
            return false;
        }
        
        return $tokenData;
        
    } catch (Exception $e) {
        return false;
    }
}

// Helper: enforce password complexity
function isPasswordComplex($password) {
    if (strlen($password) < 8) return false;
    if (!preg_match('/[a-z]/', $password)) return false;       // lowercase
    if (!preg_match('/[A-Z]/', $password)) return false;       // uppercase
    if (!preg_match('/\d/', $password)) return false;         // number
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) return false; // special
    return true;
}

if (empty($token)) {
    $error = 'Invalid or missing reset token.';
} else {
    // Verify and decode token
    $tokenData = verifyToken($token);
    
    if (!$tokenData) {
        $error = 'Invalid or expired reset token. Please request a new password reset.';
    } else {
        // Determine role and look up the correct account
        try {
            // Backward compatibility: older tokens used 'student_id' only
            if (isset($tokenData['student_id'])) {
                $tokenData['role'] = 'student';
                $tokenData['id'] = $tokenData['student_id'];
            }

            if (($tokenData['role'] ?? '') === 'student' || isset($tokenData['student_id'])) {
                $stmt = $conn->prepare("SELECT student_id, firstname, email, last_password_change FROM students WHERE student_id = ? AND email = ?");
                $studentId = isset($tokenData['student_id']) ? $tokenData['student_id'] : $tokenData['id'];
                $stmt->execute([$studentId, $tokenData['email']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $userRole = 'student';
                    $displayName = $user['firstname'] ?? 'User';
                }
            }
        } catch (Exception $e) {
            $user = null;
        }

        if (!$user) {
            $error = 'User not found. Please request a new password reset.';
        } else {
            // Invalidate token if issued before last_password_change (students only)
            $tokenIat = isset($tokenData['iat']) ? (int)$tokenData['iat'] : null;
            $lastChangeStr = $user['last_password_change'] ?? null;
            if ($tokenIat && $lastChangeStr) {
                $lastChangeTs = strtotime($lastChangeStr);
                if ($lastChangeTs && $tokenIat < $lastChangeTs) {
                    $error = 'This reset link has expired. Please request a new one.';
                }
            }
        }
    }
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && $user) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!isPasswordComplex($new_password)) {
        $error = 'Password must be at least 8 characters and include uppercase, lowercase, number, and special character.';
    } else {
        try {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            if ($userRole === 'student') {
                $stmt = $conn->prepare("UPDATE students SET password = ?, last_password_change = NOW() WHERE student_id = ?");
                $ok = $stmt->execute([$hashed_password, $user['student_id']]);
            } else {
                $ok = false;
            }

            if ($ok) {
                $success = 'Password has been reset successfully. You can now login with your new password.';
                $token = ''; // Clear token to hide form
                
                // Log successful password reset
                $logId = $user['student_id'] ?? 'unknown';
                error_log("Password successfully reset for student ID: " . $logId);
            } else {
                $error = 'Failed to update password. Please try again.';
            }
        } catch (PDOException $e) {
            error_log("Password reset database error: " . $e->getMessage());
            $error = 'Database error occurred. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            /* Match login/forgot: subtle overlay and fixed background */
            background-image: linear-gradient(rgba(255, 255, 255, 0.3), rgba(255, 255, 255, 0.3)), url('School.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        .overlay {
            background-color: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
        }
        .password-strength {
            height: 5px;
            border-radius: 3px;
            transition: all 0.3s;
        }
        .weak { background-color: #ef4444; }
        .medium { background-color: #f59e0b; }
        .strong { background-color: #10b981; }
        /* Hide built-in password reveal for consistency with custom eye toggle */
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear { display: none; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="overlay p-8 rounded-lg shadow-md w-full max-w-md">
        <div class="flex justify-center mb-4">
            <img src="Bnhslogo.png" alt="Logo" class="w-16 h-16">
        </div>
        <h2 class="text-2xl font-bold mb-4 text-center text-gray-800">Reset Password</h2>

        <?php if ($success): ?>
            <div class="text-center">
                <a href="login.php" class="bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 inline-block">
                    Return to Login
                </a>
            </div>
        <?php elseif (!$error && $user): ?>
            <p class="mb-4 text-sm text-gray-600">
                Hi <strong><?php echo htmlspecialchars($displayName ?? 'User'); ?></strong>, please enter your new password:
            </p>
            
            <form method="POST" id="reset-form">
                <div class="mb-4">
                    <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-500">
                            <i class="fas fa-lock"></i>
                        </div>
                        <input type="password" name="new_password" id="new_password" 
                               class="mt-1 block w-full pl-10 pr-10 py-2 border border-gray-300 rounded-md" 
                               required minlength="8" onkeyup="checkPasswordStrength()" placeholder="••••••••">
                        <button type="button" id="toggle-new" class="absolute inset-y-0 right-3 flex items-center text-gray-500 hover:text-gray-700" aria-label="Toggle password">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                    <div id="password-strength" class="password-strength mt-2"></div>
                    <small id="strength-text" class="text-xs text-gray-500 mt-1 block"></small>
                </div>

                <div class="mb-6">
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-500">
                            <i class="fas fa-lock"></i>
                        </div>
                        <input type="password" name="confirm_password" id="confirm_password" 
                               class="mt-1 block w-full pl-10 pr-10 py-2 border border-gray-300 rounded-md" 
                               required minlength="8" onkeyup="checkPasswordMatch()" placeholder="••••••••">
                        <button type="button" id="toggle-confirm" class="absolute inset-y-0 right-3 flex items-center text-gray-500 hover:text-gray-700" aria-label="Toggle password">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                    <small id="match-text" class="text-xs mt-1 block"></small>
                </div>

                <button type="submit" id="submit-btn" 
                        class="w-full bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 disabled:bg-gray-400">
                    Reset Password
                </button>
            </form>
        <?php endif; ?>
    </div>

    <script>
        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const strengthBar = document.getElementById('password-strength');
            const strengthText = document.getElementById('strength-text');
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            if (password.length === 0) {
                strengthBar.className = 'password-strength';
                strengthText.textContent = '';
            } else if (strength < 4) {
                strengthBar.className = 'password-strength weak';
                strengthText.textContent = 'Weak password';
                strengthText.className = 'text-xs text-red-500 mt-1 block';
            } else if (strength < 5) {
                strengthBar.className = 'password-strength medium';
                strengthText.textContent = 'Medium password';
                strengthText.className = 'text-xs text-yellow-500 mt-1 block';
            } else {
                strengthBar.className = 'password-strength strong';
                strengthText.textContent = 'Strong password';
                strengthText.className = 'text-xs text-green-500 mt-1 block';
            }
            
            checkPasswordMatch();
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('match-text');
            const submitBtn = document.getElementById('submit-btn');
            
            if (confirmPassword.length === 0) {
                matchText.textContent = '';
                matchText.className = 'text-xs mt-1 block';
            } else if (password === confirmPassword) {
                matchText.textContent = '✓ Passwords match';
                matchText.className = 'text-xs text-green-500 mt-1 block';
                submitBtn.disabled = false;
            } else {
                matchText.textContent = '✗ Passwords do not match';
                matchText.className = 'text-xs text-red-500 mt-1 block';
                submitBtn.disabled = true;
            }
        }

        // Toggle password visibility handlers (match login.php behavior)
        (function(){
            function setupToggle(inputId, btnId){
                const input = document.getElementById(inputId);
                const btn = document.getElementById(btnId);
                const icon = btn ? btn.querySelector('i') : null;
                if (!input || !btn || !icon) return;
                btn.addEventListener('click', function(){
                    const isPassword = input.getAttribute('type') === 'password';
                    input.setAttribute('type', isPassword ? 'text' : 'password');
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                });
            }
            setupToggle('new_password', 'toggle-new');
            setupToggle('confirm_password', 'toggle-confirm');
        })();
    </script>
    <script>
        // SweetAlert2 for server-side messages
        document.addEventListener('DOMContentLoaded', function() {
            const hasSuccess = <?php echo json_encode(!empty($success)); ?>;
            const hasError = <?php echo json_encode(!empty($error)); ?>;
            if (hasSuccess) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: <?php echo json_encode($success); ?>,
                    confirmButtonColor: '#2563eb'
                });
            } else if (hasError) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: <?php echo json_encode($error); ?>,
                    confirmButtonColor: '#ef4444'
                }).then(() => {
                    // Redirect to request a new reset link
                    window.location.href = 'forgot_password.php';
                });
            }
        });
    </script>
  </body>
  </html>