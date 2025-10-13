<?php
session_start();
include 'db.php';



// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['error'] = "You must be logged in as an admin.";
    header("Location: login.php");
    exit();
}

    // Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $department = $_POST['department'];
    $profilePicName = null;
    
    // Since all permissions are checked by default, we'll use the default permissions
    // No need to check selected permissions since we want all permissions active
    $permissions = [
        'approve_research_' . strtolower($department),
        'verify_students_' . strtolower($department),
        'manage_repository_' . strtolower($department),
        'manage_announcements',
        'upload_research_' . strtolower($department),
        'view_students_' . strtolower($department)
    ];

    // Validate inputs
    $errors = [];

    if (empty($fullname)) {
        $errors[] = "Fullname is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // No need to check for empty permissions as they are automatically assigned

    // Check if fullname or email already exists
    $stmt = $conn->prepare("SELECT * FROM sub_admins WHERE fullname = ? OR email = ?");
    $stmt->execute([$fullname, $email]);
    if ($stmt->rowCount() > 0) {
        $errors[] = "Fullname or email already exists";
    }

    // If no errors, handle profile picture and create sub-admin
    if (empty($errors)) {
        // Process profile picture upload if provided
        if (isset($_FILES['profile_pic']) && is_array($_FILES['profile_pic']) && ($_FILES['profile_pic']['error'] === UPLOAD_ERR_OK)) {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            // Robust MIME detection with fallbacks
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
                $profilePicName = 'subadmin_' . time() . '_' . mt_rand(1000,9999) . '_' . $safeBase . '.' . $ext;
                $dest = __DIR__ . '/images/' . $profilePicName;
                if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $dest)) {
                    $errors[] = 'Failed to save uploaded profile picture.';
                }
            } else {
                $errors[] = 'Unsupported profile picture format. Please upload JPG, PNG, or WEBP.';
            }
        } elseif (!empty($_FILES['profile_pic']['error']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Error uploading profile picture.';
        }
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $permissions_json = json_encode($permissions);
        
        try {
            // Ensure required columns exist (best-effort on hosting)
            // permissions column
            try {
                $chkPerm = $conn->prepare("SHOW COLUMNS FROM sub_admins LIKE 'permissions'");
                $chkPerm->execute();
                if ($chkPerm->rowCount() == 0) {
                    $conn->exec("ALTER TABLE sub_admins ADD COLUMN permissions TEXT NULL AFTER password");
                }
            } catch (Exception $e) { /* ignore */ }

            // department column (replaces legacy 'strand')
            try {
                $chkDept = $conn->prepare("SHOW COLUMNS FROM sub_admins LIKE 'department'");
                $chkDept->execute();
                if ($chkDept->rowCount() == 0) {
                    $conn->exec("ALTER TABLE sub_admins ADD COLUMN department VARCHAR(50) NULL AFTER permissions");
                }
            } catch (Exception $e) { /* ignore */ }
            // Best-effort migration: copy legacy strand -> department where empty
            try {
                $conn->exec("UPDATE sub_admins SET department = strand WHERE (department IS NULL OR department = '') AND strand IS NOT NULL");
            } catch (Exception $e) { /* ignore */ }

            // Ensure profile_pic column exists
            $hasProfilePic = false;
            try {
                $chk = $conn->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sub_admins' AND COLUMN_NAME = 'profile_pic'");
                $chk->execute();
                $hasProfilePic = (bool)$chk->fetchColumn();
            } catch (Exception $e) {
                $hasProfilePic = false;
            }
            if (!$hasProfilePic) {
                try {
                    $conn->exec("ALTER TABLE sub_admins ADD COLUMN profile_pic VARCHAR(255) NULL AFTER department");
                    $hasProfilePic = true;
                } catch (Exception $e) {
                    // If altering table fails, continue without profile_pic
                    $hasProfilePic = false;
                }
            }

            if ($hasProfilePic) {
                $stmt = $conn->prepare("INSERT INTO sub_admins (fullname, email, password, permissions, department, profile_pic) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$fullname, $email, $hashed_password, $permissions_json, $department, $profilePicName]);
            } else {
                $stmt = $conn->prepare("INSERT INTO sub_admins (fullname, email, password, permissions, department) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$fullname, $email, $hashed_password, $permissions_json, $department]);
            }

            $_SESSION['success'] = "Sub-admin created successfully!";
            header("Location: manage_subadmins.php");
            exit();
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Sub-Admin - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .permission-card {
            transition: all 0.3s ease;
        }
        .permission-card:hover {
            transform: translateY(-2px);
        }
        .permission-card.active {
            border: 2px solid #3b82f6;
            background-color: rgba(59, 130, 246, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen flex flex-col lg:flex-row overflow-x-hidden">
    <!-- Include the sidebar -->
    <?php include 'admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8 w-full max-w-7xl mx-auto">
        <!-- Header -->
        <header class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 mb-6 sm:mb-8 border border-gray-200">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-blue-900 flex items-center">
                        <i class="fas fa-user-plus mr-3"></i> Create Sub-Admin
                    </h1>
                    <p class="text-sm sm:text-base text-gray-600 mt-1">Add a new sub-administrator with custom permissions</p>
                </div>
            </div>
        </header>

        <?php if (isset($_SESSION['success'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    Swal.fire({
                        icon: 'success',
                        title: <?php echo json_encode($_SESSION['success']); ?>,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                });
            </script>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Action failed',
                        text: <?php echo json_encode($_SESSION['error']); ?>,
                        confirmButtonText: 'OK'
                    });
                });
            </script>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="max-w-4xl mx-auto">
            <!-- Back Button -->
            <div class="mb-6">
                <a href="manage_subadmins.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 transition duration-200">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Sub-Admins
                </a>
            </div>

            <!-- Success Message -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <span class="font-medium">Please fix the following errors:</span>
                    </div>
                    <ul class="list-disc pl-5 space-y-1">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Please fix the following',
                            html: <?php
                                $list = '<ul style="text-align:left; margin-left:1rem;">';
                                foreach ($errors as $e) { $list .= '<li>'.htmlspecialchars($e).'</li>'; }
                                $list .= '</ul>';
                                echo json_encode($list);
                            ?>,
                            confirmButtonText: 'OK'
                        });
                    });
                </script>
            <?php endif; ?>

            <!-- Create Sub-Admin Form -->
            <div class="bg-white rounded-2xl shadow-xl p-4 sm:p-6 lg:p-8 border border-gray-200">
                <form action="create_subadmin.php" method="POST" enctype="multipart/form-data" class="space-y-6 sm:space-y-8">
                    <!-- Personal Information Section -->
                    <div class="border-b border-gray-200 pb-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-user mr-3 text-blue-600"></i>
                            Personal Information
                        </h2>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                            <!-- Profile Picture FIRST on mobile -->
                            <div class="order-1 lg:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-image mr-1"></i> Profile Picture (optional)
                                </label>
                                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-4">
                                    <img id="createProfilePicPreview" src="" alt="Profile Preview" class="w-14 h-14 sm:w-16 sm:h-16 rounded-full object-cover border hidden">
                                    <div id="createProfilePicPlaceholder" class="w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-gray-200 flex items-center justify-center text-gray-500">N/A</div>
                                    <div class="w-full sm:flex-1 min-w-0">
                                        <input type="file" id="createProfilePicInput" name="profile_pic" accept="image/*" class="w-full py-2 px-3 border border-gray-300 rounded-lg">
                                        <p class="text-xs text-gray-500 mt-1">Allowed types: JPG, PNG, WEBP. Max ~5MB.</p>
                                    </div>
                                </div>
                            </div>
                            <!--Fullname -->
                            <div class="order-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-user-tag mr-1"></i> Fullname
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-500">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <input type="text" name="fullname" 
                                           value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>" 
                                           class="w-full pl-10 pr-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                                           placeholder="Enter fullname" required>
                                </div>
                            </div>
                            <!-- Email -->
                            <div class="order-3">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-envelope mr-1"></i> Email Address
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-500">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <input type="email" name="email" 
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                           class="w-full pl-10 pr-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                                           placeholder="Enter email" required>
                                </div>
                            </div>
                            <!-- Password -->
                            <div class="order-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-lock mr-1"></i> Password
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-500">
                                        <i class="fas fa-lock"></i>
                                    </div>
                                    <input type="password" name="password" id="password" 
                                           class="w-full pl-10 pr-12 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                                           placeholder="Enter password" required>
                                    <button type="button" id="togglePassword" class="absolute inset-y-0 right-3 flex items-center text-gray-500 hover:text-gray-700 transition duration-200">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                            </div>
                            <!-- Confirm Password -->
                            <div class="order-5">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-lock mr-1"></i> Confirm Password
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-500">
                                        <i class="fas fa-lock"></i>
                                    </div>
                                    <input type="password" name="confirm_password" id="confirm_password" 
                                           class="w-full pl-10 pr-12 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                                           placeholder="Confirm password" required>
                                    <button type="button" id="toggleConfirmPassword" class="absolute inset-y-0 right-3 flex items-center text-gray-500 hover:text-gray-700 transition duration-200">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <!-- Department Selection -->
                            <div class="order-6 lg:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-graduation-cap mr-1"></i> Department Assignment
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-500">
                                        <i class="fas fa-book"></i>
                                    </div>
                                    <select name="department" required
                                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 bg-white">
                                        <option value="" selected>Select Department</option>
                                        <option value="CCS">CCS (College of Computer Studies)</option>
                                        <option value="COE">COE (College of Education)</option>
                                        <option value="CBS">CBS (College of Business Studies)</option>
                                        <option value="Senior High School">Senior High School</option>
                                    </select>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">The sub-admin will be assigned to manage students in this department.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Permissions Info (auto-assigned) -->
                    <div class="border-b border-gray-200 pb-6">
                        <div class="p-4 bg-blue-50 rounded-lg border border-blue-200 text-blue-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            All required permissions will be assigned automatically based on the selected department. No manual selection is needed.
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end pt-4">
                        <button type="submit" class="w-full sm:w-auto bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-medium py-3 px-8 rounded-lg shadow-lg hover:shadow-xl transition-all duration-300 flex items-center justify-center">
                            <i class="fas fa-plus-circle mr-2"></i>
                            Create Sub-Admin
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Toggle password visibility
        function setupPasswordToggle(toggleBtnId, passwordInputId) {
            const toggleBtn = document.getElementById(toggleBtnId);
            const passwordInput = document.getElementById(passwordInputId);
            const icon = toggleBtn.querySelector('i');
            
            if (!toggleBtn || !passwordInput) return;
            
            toggleBtn.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
        }
        
        // Setup password toggles
        setupPasswordToggle('togglePassword', 'password');
        setupPasswordToggle('toggleConfirmPassword', 'confirm_password');
        
        // No permission toggling needed as all permissions are automatically assigned
    </script>
    <script>
    // Live preview for Create Sub-Admin profile picture
    (function(){
        const input = document.getElementById('createProfilePicInput');
        const preview = document.getElementById('createProfilePicPreview');
        const placeholder = document.getElementById('createProfilePicPlaceholder');
        if (!input) return;
        input.addEventListener('change', function(){
            const file = this.files && this.files[0] ? this.files[0] : null;
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e){
                    if (preview) {
                        preview.src = e.target.result;
                        preview.classList.remove('hidden');
                    }
                    if (placeholder) placeholder.classList.add('hidden');
                };
                reader.readAsDataURL(file);
            } else {
                if (preview) preview.classList.add('hidden');
                if (placeholder) placeholder.classList.remove('hidden');
            }
        });
    })();
    </script>
</body>
</html>