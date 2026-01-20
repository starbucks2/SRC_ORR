<?php
session_start();
include 'db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['error'] = "You must be logged in as an admin.";
    header("Location: admin_login.php");
    exit();
}

// Get sub-admin id (may be employee_id string or numeric id)
$param_id = isset($_GET['id']) ? trim($_GET['id']) : '';
if ($param_id === '') {
    $_SESSION['error'] = 'Invalid Research Adviser ID.';
    header('Location: manage_subadmins.php');
    exit();
}

// Fetch existing record from employees
$subadmin = null;
$allowedRoles = [
    ['role_id'=>1,'role_name'=>'DEAN','display_name'=>'Dean'],
    ['role_id'=>2,'role_name'=>'RESEARCH_ADVISER','display_name'=>'Research Adviser'],
];
try {
    // Detect columns to build robust query
    $cols = [];
    try {
        $qc = $conn->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees'");
        $qc->execute();
        $cols = $qc->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (Throwable $_) { $cols = []; }
    $hasIdCol = in_array('id', $cols, true);
    $hasFirst = in_array('firstname', $cols, true) || in_array('first_name', $cols, true);
    $hasLast  = in_array('lastname',  $cols, true) || in_array('last_name',  $cols, true);

    if ($hasIdCol) {
        $stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ? OR id = ? LIMIT 1");
        $stmt->execute([$param_id, $param_id]);
    } else {
        $stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ? LIMIT 1");
        $stmt->execute([$param_id]);
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        // Derive fields used by the form
        $fn = $row['firstname'] ?? ($row['first_name'] ?? '');
        $ln = $row['lastname']  ?? ($row['last_name']  ?? '');
        $fullname = trim($fn . ' ' . $ln);
        $department = $row['department'] ?? ($row['strand'] ?? '');
        // Determine current role textual value
        $roleText = strtoupper(str_replace(' ', '_', trim((string)($row['role'] ?? ($row['employee_type'] ?? '')))));
        $subadmin = [
            'employee_id' => $row['employee_id'] ?? ($row['id'] ?? $param_id),
            'fullname' => $fullname,
            'email' => $row['email'] ?? '',
            'profile_pic' => $row['profile_pic'] ?? '',
            'strand' => $department,
            'role_name' => ($roleText === 'DEAN' ? 'DEAN' : 'RESEARCH_ADVISER')
        ];
    }
} catch (Throwable $e) {
    $subadmin = null;
}

if (!$subadmin) {
    $_SESSION['error'] = 'Research Adviser not found.';
    header('Location: manage_subadmins.php');
    exit();
}

// We'll show flash messages from session (set by update_subadmin.php)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Sub-Admin - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .permission-card { transition: all 0.3s ease; }
        .permission-card:hover { transform: translateY(-2px); }
        .permission-card.active { border: 2px solid #3b82f6; background-color: rgba(59,130,246,0.08); }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen flex flex-col md:flex-row">
    <?php include 'admin_sidebar.php'; ?>
    <main class="flex-1 w-full p-4 sm:p-6 md:p-8">
        <header class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 mb-6 sm:mb-8 border border-gray-200">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                <div>
                    <h1 class="text-3xl font-bold text-blue-900 flex items-center"><i class="fas fa-user-edit mr-3"></i> Edit Sub-Admin</h1>
                    <p class="text-gray-600 mt-1">Update sub-administrator details and permissions</p>
                </div>
            </div>
        </header>

        <div class="max-w-4xl w-full mx-auto px-2 sm:px-0">
            <div class="mb-6">
                <a href="manage_subadmins.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 transition duration-200 text-sm sm:text-base">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Sub-Admins
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                    <div class="flex items-center mb-2"><i class="fas fa-exclamation-triangle mr-2"></i><span class="font-medium">Please fix the following errors:</span></div>
                    <ul class="list-disc pl-5 space-y-1">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-2xl shadow-xl p-4 sm:p-6 md:p-8 border border-gray-200">
                <form action="update_subadmin.php" method="POST" enctype="multipart/form-data" class="space-y-6 sm:space-y-8">
                    <input type="hidden" name="target_id" value="<?= htmlspecialchars($subadmin['employee_id']) ?>">
                    <div class="border-b border-gray-200 pb-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center"><i class="fas fa-user mr-3 text-blue-600"></i> Personal Information</h2>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                            <!-- Profile Picture FIRST on mobile -->
                            <div class="order-1 lg:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-image mr-1"></i> Profile Picture
                                </label>
                                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-4">
                                    <img id="profilePicPreview" src="<?php echo !empty($subadmin['profile_pic']) ? ('images/' . htmlspecialchars($subadmin['profile_pic'])) : '';?>" alt="Profile Preview" class="w-14 h-14 sm:w-16 sm:h-16 rounded-full object-cover border <?php echo empty($subadmin['profile_pic']) ? 'hidden' : '';?>">
                                    <div id="profilePicPlaceholder" class="w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 <?php echo !empty($subadmin['profile_pic']) ? 'hidden' : '';?>">N/A</div>
                                    <div class="w-full sm:flex-1 min-w-0">
                                        <input type="file" id="profilePicInput" name="profile_pic" accept="image/*" class="w-full py-2 px-3 border border-gray-300 rounded-lg">
                                        <p class="text-xs text-gray-500 mt-1">Allowed: JPG, PNG, WEBP. Uploading a new picture will replace the current one.</p>
                                        <?php if (!empty($subadmin['profile_pic'])): ?>
                                        <label class="inline-flex items-center mt-2 text-sm">
                                            <input type="checkbox" id="removeProfilePic" name="remove_profile_pic" value="1" class="mr-2">
                                            Remove current picture
                                        </label>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Fullname -->
                            <div class="order-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-user-tag mr-1"></i> Fullname
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-500">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <input type="text" name="fullname" value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : htmlspecialchars($subadmin['fullname'] ?? ''); ?>" class="w-full pl-10 pr-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 text-sm sm:text-base" placeholder="Enter fullname" required>
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
                                    <input type="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : htmlspecialchars($subadmin['email'] ?? ''); ?>" class="w-full pl-10 pr-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 text-sm sm:text-base" placeholder="Enter email" required>
                                </div>
                            </div>

                            <!-- Current Password -->
                            <div class="order-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Current Password <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-500">
                                        <i class="fas fa-lock"></i>
                                    </div>
                                    <input type="password" name="current_password" id="current_password" class="w-full pl-10 pr-12 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 text-sm sm:text-base" placeholder="Enter current password">
                                    <button type="button" id="toggleCurrentPassword" class="absolute inset-y-0 right-3 flex items-center text-gray-500 hover:text-gray-700"><i class="fas fa-eye"></i></button>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Required to change password (for self-edit).</p>
                            </div>

                            <!-- New Password -->
                            <div class="order-5">
                                <label class="block text-sm font-medium text-gray-700 mb-2">New Password (leave blank to keep current)</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-500">
                                        <i class="fas fa-lock"></i>
                                    </div>
                                    <input type="password" name="new_password" id="new_password" class="w-full pl-10 pr-12 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 text-sm sm:text-base" placeholder="New password">
                                    <button type="button" id="togglePassword" class="absolute inset-y-0 right-3 flex items-center text-gray-500 hover:text-gray-700"><i class="fas fa-eye"></i></button>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                            </div>

                            <!-- Confirm Password -->
                            <div class="order-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-500">
                                        <i class="fas fa-lock"></i>
                                    </div>
                                    <input type="password" name="confirm_password" id="confirm_password" class="w-full pl-10 pr-12 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 text-sm sm:text-base" placeholder="Confirm password">
                                    <button type="button" id="toggleConfirmPassword" class="absolute inset-y-0 right-3 flex items-center text-gray-500 hover:text-gray-700"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>

                            <!-- Department Selection LAST on mobile -->
                            <div class="order-7 lg:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-graduation-cap mr-1"></i> Department Assignment
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-500">
                                        <i class="fas fa-book"></i>
                                    </div>
                                    <select name="strand" required
                                           class="w-full pl-10 pr-4 py-2.5 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 bg-white text-sm sm:text-base">
                                        <?php $selected = isset($_POST['strand']) ? $_POST['strand'] : ($subadmin['strand'] ?? ''); ?>
                                        <option value="" <?= empty($selected) ? 'selected' : '' ?>>Select Department</option>
                                        <option value="CCS" <?= ($selected==='CCS' ? 'selected' : '') ?>>CCS (College of Computer Studies)</option>
                                        <option value="COE" <?= ($selected==='COE' ? 'selected' : '') ?>>COE (College of Education)</option>
                                        <option value="CBS" <?= ($selected==='CBS' ? 'selected' : '') ?>>CBS (College of Business Studies)</option>
                                        <option value="Senior High School" <?= ($selected==='Senior High School' ? 'selected' : '') ?>>Senior High School</option>
                                    </select>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">The sub-admin will be assigned to manage students in this department.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Role Selection -->
                    <div class="border-b border-gray-200 pb-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center"><i class="fas fa-id-badge mr-3 text-blue-600"></i> Role</h2>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Assign Role</label>
                            <select name="role_name" class="w-full border rounded-lg px-3 py-2">
                                <?php $currentRole = isset($_POST['role_name']) ? strtoupper(str_replace(' ','_',$_POST['role_name'])) : ($subadmin['role_name'] ?? 'RESEARCH_ADVISER'); ?>
                                <?php foreach ($allowedRoles as $r): $rname = strtoupper($r['role_name']); ?>
                                    <option value="<?= htmlspecialchars($r['role_name']) ?>" <?= ($currentRole === $rname ? 'selected' : '') ?>><?= htmlspecialchars($r['display_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Only Dean and Research Adviser are allowed.</p>
                        </div>
                    </div>

                    <div class="border-b border-gray-200 pb-6">
                        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg text-blue-800 text-sm sm:text-base">
                            <i class="fas fa-info-circle mr-2"></i>
                            Permissions are automatically managed based on the selected strand. Manual changes are not required.
                        </div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="submit" class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-medium py-3 px-8 rounded-lg shadow-lg hover:shadow-xl transition-all duration-300 flex items-center w-full sm:w-auto justify-center">
                            <i class="fas fa-save mr-2"></i> Save Changes
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
            if (!toggleBtn || !passwordInput) return;
            const icon = toggleBtn.querySelector('i');
            toggleBtn.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
        }
    setupPasswordToggle('toggleCurrentPassword', 'current_password');
    setupPasswordToggle('togglePassword', 'new_password');
    setupPasswordToggle('toggleConfirmPassword', 'confirm_password');

        document.addEventListener('DOMContentLoaded', function() {
            // Keep only password validation
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const currentPass = document.getElementById('current_password');
                    const newPass = document.getElementById('new_password');
                    const confirmPass = document.getElementById('confirm_password');
                    const currentVal = currentPass ? currentPass.value.trim() : '';
                    const newVal = newPass ? newPass.value.trim() : '';
                    const confirmVal = confirmPass ? confirmPass.value.trim() : '';

                    if (newVal !== '' || confirmVal !== '' || currentVal !== '') {
                        if (currentVal === '') {
                            e.preventDefault();
                            alert('Please enter your current password to change password.');
                            return;
                        }
                        if (newVal === '' || confirmVal === '') {
                            e.preventDefault();
                            alert('Please fill both new password fields or leave all empty.');
                            return;
                        }
                        if (newVal !== confirmVal) {
                            e.preventDefault();
                            alert('Passwords do not match.');
                            return;
                        }
                        if (newVal.length < 8) {
                            e.preventDefault();
                            alert('New password must be at least 8 characters long.');
                            return;
                        }
                    }
                });
            }
        });
    </script>
    <script>
    // Live preview for profile picture selection and remove toggle
    (function(){
        const input = document.getElementById('profilePicInput');
        const preview = document.getElementById('profilePicPreview');
        const placeholder = document.getElementById('profilePicPlaceholder');
        const removeCb = document.getElementById('removeProfilePic');
        if (input) {
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
                        if (removeCb) removeCb.checked = false; // uncheck remove if new image selected
                    };
                    reader.readAsDataURL(file);
                } else {
                    // No file selected; restore placeholder if no existing image
                    if (preview && !preview.src) preview.classList.add('hidden');
                    if (placeholder) placeholder.classList.remove('hidden');
                }
            });
        }
        if (removeCb) {
            removeCb.addEventListener('change', function(){
                if (this.checked) {
                    // Hide preview and show placeholder
                    if (preview) preview.classList.add('hidden');
                    if (placeholder) placeholder.classList.remove('hidden');
                    if (input) input.value = '';
                }
            });
        }
    })();
    </script>
</body>
</html>
