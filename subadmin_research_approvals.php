<?php
session_start();
include 'db.php';

// Check if user is logged in (either admin or sub-admin with proper permissions)
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['subadmin_id'])) {
    $_SESSION['error'] = "You must be logged in to access this page.";
    header("Location: login.php");
    exit();
}

// Check permissions for sub-admins
$can_approve_research = true; // Admins can always approve

// Detect which columns exist in employees for role filtering
try {
    $c1 = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'role'");
    $c1->execute();
    $hasRoleColEmployees = ((int)$c1->fetchColumn() > 0);
} catch (Throwable $_) { $hasRoleColEmployees = false; }
try {
    $c2 = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'employee_type'");
    $c2->execute();
    $hasEmpTypeColEmployees = ((int)$c2->fetchColumn() > 0);
} catch (Throwable $_) { $hasEmpTypeColEmployees = false; }
// Build role WHERE clause only with existing columns
$wantedRolesSql = "('RESEARCH_ADVISER','FACULTY')";
$whereRoleEmployees = "1=0"; // default safe
if ($hasRoleColEmployees && $hasEmpTypeColEmployees) {
    $whereRoleEmployees = "( UPPER(REPLACE(TRIM(role), ' ', '_')) IN $wantedRolesSql OR UPPER(REPLACE(TRIM(employee_type), ' ', '_')) IN $wantedRolesSql )";
} elseif ($hasRoleColEmployees) {
    $whereRoleEmployees = "UPPER(REPLACE(TRIM(role), ' ', '_')) IN $wantedRolesSql";
} elseif ($hasEmpTypeColEmployees) {
    $whereRoleEmployees = "UPPER(REPLACE(TRIM(employee_type), ' ', '_')) IN $wantedRolesSql";
}
if (isset($_SESSION['subadmin_id'])) {
    // Need department to evaluate department-scoped permission key
    $assigned_department_tmp = '';
    try {
        $sqlDept1 = "SELECT department FROM employees WHERE employee_id = ? AND $whereRoleEmployees LIMIT 1";
        $tmpStmt = $conn->prepare($sqlDept1);
        $tmpStmt->execute([$_SESSION['subadmin_id']]);
        $assigned_department_tmp = (string)($tmpStmt->fetchColumn() ?: '');
    } catch (Exception $e) { $assigned_department_tmp = ''; }

    $permissions = json_decode($_SESSION['permissions'] ?? '[]', true);
    $permLower = array_map('strtolower', is_array($permissions) ? $permissions : []);
    $departmentKey = strtolower($assigned_department_tmp);
    $can_approve_research = in_array('approve_research', $permLower, true) || in_array('approve_research_' . $departmentKey, $permLower, true);
    // Allow viewing even without permission - just disable approve button
}

// Approve research submission (only for users with permission)
if (isset($_POST['approve']) && $can_approve_research) {
    $research_id = $_POST['research_id'];

    // Update status to approved (1)
    $approve_stmt = $conn->prepare("UPDATE research_submission SET status = 1 WHERE id = ?");
    if ($approve_stmt->execute([$research_id])) {
        $_SESSION['success'] = "Research approved successfully.";
    } else {
        $_SESSION['error'] = "Failed to approve research.";
    }

    header("Location: subadmin_research_approvals.php");
    exit();
}

// Archive research submission
if (isset($_POST['archive'])) {
    $research_id = $_POST['research_id'];

    // Prefer status-based archive (2 = Archived)
    $ok = false;
    try {
        $archive_stmt = $conn->prepare("UPDATE research_submission SET status = 2 WHERE id = ?");
        $ok = $archive_stmt->execute([$research_id]);
        // Best-effort: also set is_archived = 1 if the column exists
        try { $conn->exec("UPDATE research_submission SET is_archived = 1 WHERE id = " . $conn->quote($research_id)); } catch (Throwable $e) { /* ignore */ }
    } catch (Throwable $e) { $ok = false; }

    if ($ok) {
        $_SESSION['success'] = "Research archived successfully.";
    } else {
        $_SESSION['error'] = "Failed to archive research.";
    }

    // Return to the subadmin approvals page
    header("Location: subadmin_research_approvals.php");
    exit();
}

// Get the subadmin's department if they are logged in
$assigned_department = '';
if (isset($_SESSION['subadmin_id'])) {
    $sqlDept2 = "SELECT department FROM employees WHERE employee_id = ? AND $whereRoleEmployees LIMIT 1";
    $department_stmt = $conn->prepare($sqlDept2);
    $department_stmt->execute([$_SESSION['subadmin_id']]);
    $assigned_department = $department_stmt->fetchColumn();
}

// Fetch pending research submissions based on user role and department
if (isset($_SESSION['subadmin_id']) && !empty($assigned_department)) {
    // For subadmin, only show research from their department
    $research_stmt = $conn->prepare("SELECT * FROM research_submission WHERE status = 0 AND department LIKE ?");
    $searchDepartment = '%' . $assigned_department . '%';
    $research_stmt->execute([$searchDepartment]);
} else {
    // For admin, show all pending research
    $research_stmt = $conn->prepare("SELECT * FROM research_submission WHERE status = 0");
    $research_stmt->execute();
}
$pending_research = $research_stmt->fetchAll(PDO::FETCH_ASSOC);

// No need to fetch student data since section and group_number columns don't exist

// Define the base upload directory
$uploadDir = 'research_documents/'; // Relative to your script location
$absoluteUploadDir = realpath(__DIR__ . '/' . $uploadDir) . '/';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research Approvals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .sidebar-link:hover {
            color: #facc15;
        }
    </style>
</head>
<body class="bg-gray-100 flex min-h-screen">
    <!-- Sidebar -->
    <?php include 'subadmin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-6">
        <!-- Header -->
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
            <h2 class="text-3xl font-bold text-gray-800 mb-4 md:mb-0 flex items-center">
                <i class="fas fa-check-double mr-3"></i> Research Approvals
            </h2>
        </header>

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

        <!-- Info Message for Sub-Admins without Permission -->
        <?php if (isset($_SESSION['subadmin_id']) && !$can_approve_research): ?>
            <div class="mb-6 p-4 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded-lg flex items-center">
                <i class="fas fa-info-circle mr-2"></i>
                <span>You can view pending research submissions but cannot approve them.</span>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="overflow-x-auto hidden md:block">
                <table class="w-full border-collapse">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="border border-gray-300 px-4 py-3 text-left text-sm font-semibold text-gray-700">Title</th>
                            <th class="border border-gray-300 px-4 py-3 text-left text-sm font-semibold text-gray-700">Abstract</th>
                            <th class="border border-gray-300 px-4 py-3 text-left text-sm font-semibold text-gray-700">Year</th>
                            <th class="border border-gray-300 px-4 py-3 text-left text-sm font-semibold text-gray-700">Department</th>
                            <th class="border border-gray-300 px-4 py-3 text-left text-sm font-semibold text-gray-700">Members</th>
                            <th class="border border-gray-300 px-4 py-3 text-left text-sm font-semibold text-gray-700">Document</th>
                            <th class="border border-gray-300 px-4 py-3 text-left text-sm font-semibold text-gray-700">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (count($pending_research) > 0): ?>
                            <?php foreach ($pending_research as $research): ?>
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <td class="border border-gray-300 px-4 py-3 text-gray-900">
                                        <?= htmlspecialchars($research['title']); ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-3 text-gray-700 max-w-xs truncate" title="<?= htmlspecialchars($research['abstract']); ?>">
                                        <?= htmlspecialchars($research['abstract']); ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-3 text-gray-700">
                                        <?= htmlspecialchars($research['year']); ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-3 text-gray-700">
                                        <?= htmlspecialchars($research['department'] ?? '-'); ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-3 text-gray-700">
                                        <?= htmlspecialchars($research['members']); ?>
                                    </td>
                                    
                                    <td class="border border-gray-300 px-4 py-3">
                                        <?php
                                        $docPath = '';
                                        if (!empty($research['document'])) {
                                            $cleanPath = ltrim($research['document'], '/');
                                            if (strpos($cleanPath, 'uploads/') === 0) {
                                                $docPath = $cleanPath;
                                            } else {
                                                $docPath = 'uploads/research_documents/' . $cleanPath;
                                            }
                                        }
                                        ?>
                                        <?php if (!empty($docPath)): ?>
                                            <a href="<?= htmlspecialchars($docPath); ?>" 
                                               target="_blank" 
                                               class="inline-flex items-center text-blue-600 hover:text-blue-800">
                                                <i class="fas fa-file-pdf mr-1"></i> View PDF
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-500">No file</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <!-- Approve Button (only for users with permission) -->
                                            <?php if ($can_approve_research): ?>
                                                <form method="POST" action="subadmin_research_approvals.php" class="inline">
                                                    <input type="hidden" name="research_id" value="<?= $research['id']; ?>">
                                                    <input type="hidden" name="approve" value="1">
                                                    <button type="button" class="js-approve inline-flex items-center bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded transition duration-200">
                                                        <i class="fas fa-check mr-1"></i> Approve
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="inline-flex items-center bg-gray-300 text-gray-600 px-3 py-1 rounded cursor-not-allowed">
                                                    <i class="fas fa-lock mr-1"></i> No Permission
                                                </span>
                                            <?php endif; ?>
                                            
                                            <!-- Archive Button (available to all) -->
                                            <form method="POST" action="subadmin_research_approvals.php" class="inline">
                                                <input type="hidden" name="research_id" value="<?= $research['id']; ?>">
                                                <input type="hidden" name="archive" value="1">
                                                <button type="button" class="js-archive inline-flex items-center bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded transition duration-200">
                                                    <i class="fas fa-archive mr-1"></i> Archive
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-8 text-gray-600">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-folder-open text-4xl mb-2 opacity-50"></i>
                                        <span class="text-lg">No pending research submissions.</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Mobile / Tablet Card List -->
            <div class="block md:hidden">
                <?php if (count($pending_research) > 0): ?>
                    <ul class="divide-y divide-gray-200">
                        <?php foreach ($pending_research as $research): ?>
                            <?php
                                $docPath = '';
                                if (!empty($research['document'])) {
                                    $cleanPath = ltrim($research['document'], '/');
                                    if (strpos($cleanPath, 'uploads/') === 0) {
                                        $docPath = $cleanPath;
                                    } else {
                                        $docPath = 'uploads/research_documents/' . $cleanPath;
                                    }
                                }
                            ?>
                            <li class="p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="text-base font-semibold text-gray-900 truncate"><?= htmlspecialchars($research['title']); ?></h3>
                                        <p class="text-xs text-gray-500 mt-0.5">Year: <span class="font-medium text-gray-700"><?= htmlspecialchars($research['year']); ?></span> • Department: <span class="font-medium text-gray-700"><?= htmlspecialchars($research['department'] ?? '-'); ?></span></p>
                                    </div>
                                    <div class="shrink-0">
                                        <?php if (!empty($docPath)): ?>
                                            <a href="<?= htmlspecialchars($docPath); ?>" target="_blank" class="inline-flex items-center text-blue-600 hover:text-blue-800 text-sm">
                                                <i class="fas fa-file-pdf mr-1"></i> PDF
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-500">No file</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if (!empty($research['abstract'])): ?>
                                    <p class="mt-2 text-sm text-gray-700 line-clamp-3" title="<?= htmlspecialchars($research['abstract']); ?>"><?= htmlspecialchars($research['abstract']); ?></p>
                                <?php endif; ?>
                                <p class="mt-2 text-xs text-gray-600"><span class="font-semibold">Members:</span> <?= htmlspecialchars($research['members']); ?></p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <?php if ($can_approve_research): ?>
                                        <form method="POST" action="subadmin_research_approvals.php" class="inline">
                                            <input type="hidden" name="research_id" value="<?= $research['id']; ?>">
                                            <input type="hidden" name="approve" value="1">
                                            <button type="button" class="js-approve inline-flex items-center bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">
                                                <i class="fas fa-check mr-1"></i> Approve
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="inline-flex items-center bg-gray-300 text-gray-600 px-3 py-1 rounded text-sm">
                                            <i class="fas fa-lock mr-1"></i> No Permission
                                        </span>
                                    <?php endif; ?>
                                    <form method="POST" action="subadmin_research_approvals.php" class="inline">
                                        <input type="hidden" name="research_id" value="<?= $research['id']; ?>">
                                        <input type="hidden" name="archive" value="1">
                                        <button type="button" class="js-archive inline-flex items-center bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm">
                                            <i class="fas fa-archive mr-1"></i> Archive
                                        </button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="p-6 text-center text-gray-600">
                        <div class="flex flex-col items-center">
                            <i class="fas fa-folder-open text-3xl mb-2 opacity-50"></i>
                            <span>No pending research submissions.</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <script>
    // Delegated handlers for Approve/Archive with SweetAlert2
    (function(){
      document.addEventListener('click', function(e){
        const approveBtn = e.target.closest('.js-approve');
        const archiveBtn = e.target.closest('.js-archive');
        if (!approveBtn && !archiveBtn) return;
        e.preventDefault();
        const form = (approveBtn || archiveBtn).closest('form');
        if (!form) return;
        const isApprove = !!approveBtn;
        const opts = isApprove ? {
          title: 'Approve this research?',
          text: 'This will mark the submission as approved.',
          confirmButtonText: 'Yes, approve it!'
        } : {
          title: 'Archive this research?',
          text: 'This will move the submission to archive.',
          confirmButtonText: 'Yes, archive it!'
        };
        Swal.fire({
          title: opts.title,
          text: opts.text,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: opts.confirmButtonText,
          cancelButtonText: 'Cancel',
          reverseButtons: true
        }).then((result) => {
          if (result.isConfirmed) form.submit();
        });
      });
    })();
    </script>
</body>
</html>