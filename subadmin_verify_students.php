<?php
session_start();
include 'db.php';

// Check if user is logged in (either admin or sub-admin with proper permissions)
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['subadmin_id'])) {
    $_SESSION['error'] = "You must be logged in to access this page.";
    header("Location: login.php");
    exit();
}

// Get subadmin's department and profile pic if they are a subadmin (needed for permission evaluation)
$subadmin_department = '';
$subadmin_profile_pic = '';
if (isset($_SESSION['subadmin_id'])) {
    try {
        $stmtDept = $conn->prepare("SELECT department, profile_pic FROM sub_admins WHERE id = ?");
        $stmtDept->execute([$_SESSION['subadmin_id']]);
        $result = $stmtDept->fetch(PDO::FETCH_ASSOC);
        $subadmin_department = $result['department'] ?? '';
        $subadmin_profile_pic = $result['profile_pic'] ?? '';
        
        // Update session with fresh profile pic
        if ($subadmin_profile_pic) {
            $_SESSION['subadmin_profile_pic'] = $subadmin_profile_pic;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error fetching subadmin department: " . $e->getMessage();
    }
}

// Check permissions for sub-admins
$can_verify_students = true; // Admins can always verify
if (isset($_SESSION['subadmin_id'])) {
    $permissions = json_decode($_SESSION['permissions'] ?? '[]', true);
    $permLower = array_map('strtolower', is_array($permissions) ? $permissions : []);
    $deptKey = strtolower($subadmin_department);
    $can_verify_students = in_array('verify_students', $permLower, true) || in_array('verify_students_' . $deptKey, $permLower, true);
    // Allow viewing even without permission - just disable approve/reject buttons
}


// Fetch unverified students based on user role
try {
    if (isset($_SESSION['subadmin_id']) && !empty($subadmin_department)) {
        // For subadmin, only show students from their department
        $stmt = $conn->prepare("SELECT * FROM students WHERE is_verified = 0 AND TRIM(LOWER(COALESCE(department,''))) = TRIM(LOWER(?)) ORDER BY created_at DESC");
        $stmt->execute([$subadmin_department]);
    } else {
        // For admin, show all unverified students
        $stmt = $conn->prepare("SELECT * FROM students WHERE is_verified = 0 ORDER BY created_at DESC");
        $stmt->execute();
    }
    $unverified_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching students: " . $e->getMessage();
    $unverified_students = [];
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Students</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100 flex min-h-screen">
    <!-- Sidebar -->
    <?php include 'subadmin_sidebar.php'; ?>


    <!-- Main Content -->
    <main class="flex-1 p-6">
        <?php
            // Prepare profile display
            $displayName = isset($_SESSION['admin_id'])
                ? ($_SESSION['admin_name'] ?? 'Administrator')
                : ($_SESSION['subadmin_name'] ?? 'Research Adviser');
            $displayPic = isset($_SESSION['admin_id'])
                ? ($_SESSION['admin_profile_pic'] ?? '')
                : ($subadmin_profile_pic ?: ($_SESSION['subadmin_profile_pic'] ?? ''));
            $displayPicUrl = $displayPic ? ('images/' . htmlspecialchars($displayPic)) : 'images/default.jpg';
        ?>
        <!-- Header -->
        <header class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 mb-6 border border-gray-200">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <h2 class="text-2xl sm:text-3xl font-extrabold text-blue-900 flex items-center">
                    <i class="fas fa-user-shield mr-3"></i>
                    Verify Students
                </h2>
                <div class="flex items-center gap-3 sm:gap-4 ml-auto">
                    <div class="text-right hidden sm:block">
                        <p class="text-xs text-gray-500">Welcome back,</p>
                        <p class="text-sm sm:text-base font-semibold text-gray-800 truncate max-w-[50vw] sm:max-w-none"><?=$displayName?></p>
                    </div>
                    <img src="<?=$displayPicUrl?>" alt="Profile" class="w-10 h-10 sm:w-12 sm:h-12 rounded-full object-cover border-2 border-blue-500 shadow" />
                </div>
            </div>
        </header>

        <!-- Department Information Banner -->
        <?php if (isset($_SESSION['subadmin_id']) && !empty($subadmin_department)): ?>
            <div class="mb-6 p-4 bg-blue-100 border border-blue-400 text-blue-700 rounded-lg flex items-center">
                <i class="fas fa-info-circle mr-2"></i>
                <span>Showing unverified students from <strong><?= htmlspecialchars($subadmin_department) ?> Department</strong></span>
            </div>
        <?php endif; ?>

        <!-- Info Message for Sub-Admins without Permission -->
        <?php if (isset($_SESSION['subadmin_id']) && !$can_verify_students): ?>
            <div class="mb-6 p-4 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded-lg dark:bg-yellow-900 dark:border-yellow-700 dark:text-yellow-200 flex items-center">
                <i class="fas fa-info-circle mr-2"></i>
                <span>You can view unverified students but cannot verify them.</span>
            </div>
        <?php endif; ?>

        <!-- SweetAlert2: Flash messages -->
        <?php 
        $__flash_success = $_SESSION['success'] ?? null;
        $__flash_error = $_SESSION['error'] ?? null;
        unset($_SESSION['success'], $_SESSION['error']);
        ?>
        <script>
        (function(){
          const successMsg = <?php echo json_encode($__flash_success); ?>;
          const errorMsg = <?php echo json_encode($__flash_error); ?>;
          if (successMsg) {
            Swal.fire({
              icon: 'success',
              title: 'Success',
              text: successMsg,
              timer: 2000,
              showConfirmButton: false
            });
          }
          if (errorMsg) {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: errorMsg
            });
          }
        })();
        </script>

        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="overflow-x-auto hidden xl:block">
                <table class="w-full border-collapse">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="border border-gray-300 px-4 py-3 text-left text-sm font-semibold text-gray-700">Profile</th>
                            <th class="border border-gray-300 px-4 py-3 text-left text-sm font-semibold text-gray-700">Name</th>
                            <th class="border border-gray-300 px-4 py-3 text-left text-sm font-semibold text-gray-700">Email</th>
                            <th class="border border-gray-300 px-4 py-3 text-left text-sm font-semibold text-gray-700">Student Number</th>
                            <th class="border border-gray-300 px-4 py-3 text-left text-sm font-semibold text-gray-700">Department</th>
                            <th class="border border-gray-300 px-4 py-3 text-left text-sm font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php if (count($unverified_students) > 0): ?>
                            <?php foreach ($unverified_students as $student): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition duration-150">
                                    <td class="border border-gray-300 dark:border-gray-600 px-4 py-3">
                                        <?php $pic = !empty($student['profile_pic']) ? 'images/' . htmlspecialchars($student['profile_pic']) : 'images/default.jpg'; ?>
                                        <img src="<?= $pic ?>" alt="Profile" class="w-10 h-10 rounded-full object-cover border">
                                    </td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-4 py-3">
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            <?= htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?>
                                        </div>
                                    </td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-gray-700 dark:text-gray-300">
                                        <?= htmlspecialchars($student['email']); ?>
                                    </td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-gray-700 dark:text-gray-300">
                                        <?= htmlspecialchars($student['student_id'] ?? ''); ?>
                                    </td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-gray-700 dark:text-gray-300">
                                        <?= htmlspecialchars($student['department'] ?? ($student['strand'] ?? '')); ?>
                                    </td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-4 py-2">
                                        <div class="flex flex-col space-y-1.5">
                                            <!-- View Profile Button -->
                                            <button type="button" 
                                                onclick="showProfileModal('<?= htmlspecialchars(addslashes($student['firstname'])) ?>','<?= htmlspecialchars(addslashes($student['lastname'])) ?>','<?= htmlspecialchars(addslashes($student['email'])) ?>','<?= htmlspecialchars(addslashes($student['student_id'] ?? '')) ?>','<?= htmlspecialchars(addslashes($student['department'] ?? ($student['strand'] ?? ''))) ?>','<?= htmlspecialchars(addslashes($student['profile_pic'])) ?>')" 
                                                class="inline-flex items-center justify-center w-full bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-sm font-medium shadow hover:shadow-md transform hover:-translate-y-0.5 transition duration-200">
                                                <i class="fas fa-user mr-1.5"></i> View Profile
                                            </button>
                                            
                                            <!-- Approve Button -->
                                            <form action="approve_student.php" method="POST" class="w-full">
                                                <input type="hidden" name="email" value="<?= htmlspecialchars($student['email']); ?>">
                                                <button type="button"
                                                    class="js-approve-student inline-flex items-center justify-center w-full bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded text-sm font-medium shadow hover:shadow-md transform hover:-translate-y-0.5 transition duration-200">
                                                    <i class="fas fa-check mr-1.5"></i> Approve
                                                </button>
                                            </form>
                                            
                                            <!-- Reject Button -->
                                            <form action="reject_student.php" method="POST" class="w-full">
                                                <input type="hidden" name="student_id" value="<?= $student['student_id']; ?>">
                                                <button type="button"
                                                    class="js-reject-student inline-flex items-center justify-center w-full bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded text-sm font-medium shadow hover:shadow-md transform hover:-translate-y-0.5 transition duration-200">
                                                    <i class="fas fa-times mr-1.5"></i> Reject
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-8 text-gray-600 dark:text-gray-400">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-users text-4xl mb-2 opacity-50"></i>
                                        <?php if (isset($_SESSION['subadmin_id'])): ?>
                                            <span class="text-lg">No <?= htmlspecialchars($subadmin_department) ?> students waiting for verification.</span>
                                        <?php else: ?>
                                            <span class="text-lg">No students waiting for verification.</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Mobile / Tablet Card List -->
            <div class="xl:hidden grid grid-cols-1 gap-3 p-4">
                <?php if (count($unverified_students) > 0): ?>
                    <?php foreach ($unverified_students as $student): ?>
                        <?php $pic = !empty($student['profile_pic']) ? 'images/' . htmlspecialchars($student['profile_pic']) : 'images/default.jpg'; ?>
                        <div class="bg-white rounded-lg shadow p-4">
                            <div class="flex items-start gap-3">
                                <img src="<?= $pic ?>" alt="Profile" class="w-12 h-12 rounded-full object-cover border">
                                <div class="min-w-0">
                                    <h4 class="text-base font-semibold text-gray-900 truncate"><?= htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?></h4>
                                    <p class="text-xs text-gray-500">Email: <span class="font-medium text-gray-700"><?= htmlspecialchars($student['email']); ?></span></p>
                                    <p class="text-xs text-gray-500">Student #: <span class="font-medium text-gray-700"><?= htmlspecialchars($student['student_id'] ?? 'N/A'); ?></span></p>
                                    <p class="text-xs text-gray-500">Department: <span class="font-medium text-gray-700"><?= htmlspecialchars($student['department'] ?? ($student['strand'] ?? 'N/A')); ?></span></p>
                                </div>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <button type="button" 
                                    onclick="showProfileModal('<?= htmlspecialchars(addslashes($student['firstname'])) ?>','<?= htmlspecialchars(addslashes($student['lastname'])) ?>','<?= htmlspecialchars(addslashes($student['email'])) ?>','<?= htmlspecialchars(addslashes($student['student_id'] ?? '')) ?>','<?= htmlspecialchars(addslashes($student['department'] ?? ($student['strand'] ?? ''))) ?>','<?= htmlspecialchars(addslashes($student['profile_pic'])) ?>')" 
                                    class="inline-flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm font-medium shadow">
                                    <i class="fas fa-user mr-1"></i> View
                                </button>
                                <form action="approve_student.php" method="POST" class="inline">
                                    <input type="hidden" name="email" value="<?= htmlspecialchars($student['email']); ?>">
                                    <button type="button" class="js-approve-student inline-flex items-center justify-center bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded text-sm font-medium shadow">
                                        <i class="fas fa-check mr-1"></i> Approve
                                    </button>
                                </form>
                                <form action="reject_student.php" method="POST" class="inline col-span-2">
                                    <input type="hidden" name="student_id" value="<?= $student['student_id']; ?>">
                                    <button type="button" class="js-reject-student inline-flex items-center justify-center w-full bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded text-sm font-medium shadow">
                                        <i class="fas fa-times mr-1"></i> Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-gray-600 p-6">
                        <div class="flex flex-col items-center">
                            <i class="fas fa-users text-3xl mb-2 opacity-50"></i>
                            <span>No students waiting for verification.</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </main>
    <!-- Profile Modal -->
    <div id="profileModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 hidden" onclick="backgroundCloseModal(event)">
        <div class="bg-white rounded-lg shadow-2xl p-10 max-w-md w-full relative flex flex-col items-center" onclick="event.stopPropagation()">
            <button onclick="closeProfileModal()" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            <img id="modalProfilePic" src="" alt="Profile Picture" class="w-40 h-40 rounded-full border-4 border-blue-500 mb-4 object-cover shadow-lg">
            <h3 id="modalName" class="text-2xl font-bold mb-2 text-center"></h3>
            <p id="modalEmail" class="text-gray-700 mb-1 text-center"></p>
            <p id="modalStudentNumber" class="text-gray-700 mb-1 text-center"></p>
            <p id="modalDepartment" class="text-gray-700 mb-1 text-center"></p>
        </div>
    </div>
    <script>
        function showProfileModal(firstname, lastname, email, studentNumber, department, profilePic) {
            document.getElementById('modalName').textContent = firstname + ' ' + lastname;
            document.getElementById('modalEmail').textContent = 'Email: ' + email;
            document.getElementById('modalStudentNumber').textContent = 'Student Number: ' + (studentNumber || 'N/A');
            document.getElementById('modalDepartment').textContent = 'Department: ' + (department || 'N/A');
            document.getElementById('modalProfilePic').src = profilePic ? 'images/' + profilePic : 'images/default.jpg';
            document.getElementById('profileModal').classList.remove('hidden');
        }
        function closeProfileModal() {
            document.getElementById('profileModal').classList.add('hidden');
        }
        function backgroundCloseModal(event) {
            if (event.target.id === 'profileModal') {
                closeProfileModal();
            }
        }
        // Delegated SweetAlert2 confirm for Approve/Reject
        document.addEventListener('click', function(e){
            const approveBtn = e.target.closest('.js-approve-student');
            const rejectBtn = e.target.closest('.js-reject-student');
            if (!approveBtn && !rejectBtn) return;
            e.preventDefault();
            const form = (approveBtn || rejectBtn).closest('form');
            if (!form) return;
            const isApprove = !!approveBtn;
            const cfg = isApprove ? {
                title: 'Approve this student?',
                text: 'This will verify the student account.',
                confirmButtonText: 'Yes, approve'
            } : {
                title: 'Reject this student?',
                text: 'This will reject and may notify the student.',
                confirmButtonText: 'Yes, reject'
            };
            Swal.fire({
                title: cfg.title,
                text: cfg.text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: cfg.confirmButtonText,
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((res) => {
                if (res.isConfirmed) form.submit();
            });
        });

    </script>
</body>
</html>