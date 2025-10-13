<?php
session_start();
include 'db.php';
// Ensure Philippine time across this page
date_default_timezone_set('Asia/Manila');


// Check if sub-admin is logged in
if (!isset($_SESSION['subadmin_id'])) {
    $_SESSION['error'] = "You must be logged in as a sub-admin.";
    header("Location: login.php");
    exit();
}

// Check if user is sub-admin or admin
$is_admin = isset($_SESSION['admin_id']);
$is_subadmin = isset($_SESSION['subadmin_id']);

if (!$is_admin && !$is_subadmin) {
    $_SESSION['error'] = "You must be logged in as an admin or sub-admin.";
    header("Location: login.php");
    exit();
}

// For sub-admin, get their information from the database
$permissions = [];
$my_department = '';

if ($is_subadmin) {
    try {
        $stmt = $conn->prepare("SELECT permissions, department, profile_pic FROM sub_admins WHERE id = ?");
        $stmt->execute([$_SESSION['subadmin_id']]);
        $subadmin_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($subadmin_data) {
            $permissions = json_decode($subadmin_data['permissions'] ?? '[]', true);
            // Prefer department; fallback to legacy strand
            $my_department = $subadmin_data['department'] ?? '';
            if ($my_department === '' || $my_department === null) { $my_department = $subadmin_data['strand'] ?? ''; }
            // Normalize department value
            $depNorm = strtolower(trim((string)$my_department));
            $depMap = [
                'ccs' => 'CCS', 'college of computer studies' => 'CCS', 'computer studies' => 'CCS',
                'cbs' => 'CBS', 'college of business studies' => 'CBS', 'business studies' => 'CBS',
                'coe' => 'COE', 'college of education' => 'COE', 'education' => 'COE',
                'senior high school' => 'Senior High School', 'shs' => 'Senior High School', 'senior high' => 'Senior High School'
            ];
            if (isset($depMap[$depNorm])) { $my_department = $depMap[$depNorm]; }
            // Last resort: infer department from permissions like verify_students_ccs
            if ($my_department === '' || $my_department === null) {
                $pl = array_map('strtolower', is_array($permissions) ? $permissions : []);
                $map = [ 'ccs' => 'CCS', 'cbs' => 'CBS', 'coe' => 'COE', 'senior high school' => 'Senior High School', 'shs' => 'Senior High School' ];
                foreach ($pl as $p) {
                    foreach ($map as $k => $val) {
                        $needle = '_' . $k;
                        $nlen = strlen($needle);
                        if ($nlen > 0 && substr_compare($p, $needle, -$nlen) === 0) { $my_department = $val; break 2; }
                    }
                }
            }
            // Fallback: use session department if previously set this session
            if ($my_department === '' || $my_department === null) {
                if (!empty($_SESSION['department'])) { $my_department = $_SESSION['department']; }
            }
            $profile_pic = $subadmin_data['profile_pic'] ?? null;
            
            // Update session with fresh data
            $_SESSION['permissions'] = $subadmin_data['permissions'];
            $_SESSION['department'] = $my_department;
            if ($profile_pic) { $_SESSION['sub_profile_pic'] = $profile_pic; }
        }
    } catch (PDOException $e) {
        // Log error and continue with empty permissions
        error_log("Error fetching sub-admin data: " . $e->getMessage());
    }
} else {
    // For admin, give full permissions
    $permissions = ['approve_research', 'verify_students', 'manage_repository', 'manage_announcements', 'upload_research'];
}

// Permissions (department-based)
// Normalize permission checks to lowercase to match stored keys like approve_research_ccs
$permSetLower = array_map('strtolower', is_array($permissions) ? $permissions : []);
$deptKeyLower = strtolower($my_department ?? '');
$can_approve_research = in_array('approve_research_' . $deptKeyLower, $permSetLower, true) || in_array('approve_research', $permSetLower, true);
$can_verify_students = in_array('verify_students_' . $deptKeyLower, $permSetLower, true) || in_array('verify_students', $permSetLower, true);
$can_manage_repository = in_array('manage_repository_' . $deptKeyLower, $permSetLower, true) || in_array('manage_repository', $permSetLower, true);
$can_manage_announcements = in_array('manage_announcements', $permSetLower, true);
$can_upload_research = in_array('upload_research_' . $deptKeyLower, $permSetLower, true) || in_array('upload_research', $permSetLower, true);

// Initialize variables
$verified_students = 0;
$total_students = 0;
$total_submissions = 0;
$recent_research = [];
$research_stats = ['approved' => 0, 'pending' => 0];
// Student activity logs (latest entries)
$student_activity_logs = [];
$unread_student_logs = 0;
// Pending queues
$pending_unverified = 0;
$pending_research = 0;
// Students in my department (count only)
$dept_student_count = 0;
// For research advisers (sub-admin), always use department-scoped analytics; admins see global
$useDept = ($is_subadmin && !empty($my_department));

try {
    // Research stats (global or department)
    if ($useDept) {
        $deptKey = strtolower(trim($my_department));
        $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM research_submission 
            WHERE TRIM(LOWER(COALESCE(department,''))) = TRIM(LOWER(?))
            GROUP BY status");
        $stmt->execute([$deptKey]);
    } else {
        $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM research_submission GROUP BY status");
        $stmt->execute();
    }
    $raw_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $research_stats = [
        'approved' => $raw_stats[1] ?? 0,
        'pending' => $raw_stats[0] ?? 0
    ];
    $total_submissions = array_sum($research_stats);

    // Recent research (global or department)
    if ($useDept) {
        $deptKey = strtolower(trim($my_department));
        $stmt = $conn->prepare("SELECT rs.title, COALESCE(CONCAT(s.firstname, ' ', s.lastname), 'Admin/Sub-Admin Upload') AS author, 
                               rs.submission_date, rs.status, rs.department AS dept 
                               FROM research_submission rs 
                               LEFT JOIN students s ON rs.student_id = s.student_id 
                               WHERE TRIM(LOWER(COALESCE(rs.department,''))) = TRIM(LOWER(?))
                               ORDER BY rs.submission_date DESC LIMIT 5");
        $stmt->execute([$deptKey]);
    } else {
        $stmt = $conn->prepare("SELECT rs.title, COALESCE(CONCAT(s.firstname, ' ', s.lastname), 'Admin/Sub-Admin Upload') AS author, 
                               rs.submission_date, rs.status, COALESCE(rs.department, rs.strand) AS dept
                                FROM research_submission rs 
                                LEFT JOIN students s ON rs.student_id = s.student_id 
                                ORDER BY rs.submission_date DESC LIMIT 5");
        $stmt->execute();
    }
    $recent_research = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Student Activity Logs (latest 30)
    // For sub-admins: show only activities from students within their department
    if ($is_subadmin && $my_department) {
        $deptKey = strtolower(trim($my_department));
        $stmt = $conn->prepare("SELECT a.id, a.actor_id, a.action, a.details, a.created_at, s.firstname, s.lastname
                                 FROM activity_logs a
                                 LEFT JOIN students s ON CAST(a.actor_id AS CHAR) = CAST(s.student_id AS CHAR)
                                 WHERE a.actor_type = 'student' AND TRIM(LOWER(COALESCE(s.department,''))) = TRIM(LOWER(?))
                                 ORDER BY a.created_at DESC, a.id DESC
                                 LIMIT 30");
        $stmt->execute([$deptKey]);
    } else {
        $stmt = $conn->prepare("SELECT a.id, a.actor_id, a.action, a.details, a.created_at, s.firstname, s.lastname, s.strand
                                 FROM activity_logs a
                                 LEFT JOIN students s ON CAST(a.actor_id AS CHAR) = CAST(s.student_id AS CHAR)
                                 WHERE a.actor_type = 'student'
                                 ORDER BY a.created_at DESC, a.id DESC
                                 LIMIT 30");
        $stmt->execute();
    }
    $student_activity_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Compute unread count using session marker (shared approach with admin)
    $lastView = $_SESSION['logs_last_view'] ?? null;
    if ($lastView) {
        foreach ($student_activity_logs as $lg) {
            if (strtotime($lg['created_at']) > strtotime($lastView)) { $unread_student_logs++; }
        }
    } else {
        $unread_student_logs = count($student_activity_logs);
    }
    if (!empty($_SESSION['logs_viewed_ack'])) { $unread_student_logs = 0; }

    // Student verification stats (global or department)
    if ($useDept) {
        $deptKey = strtolower(trim($my_department));
        $whereDept = 'TRIM(LOWER(COALESCE(department,\'\'))) = TRIM(LOWER(?))';
        $params = [$deptKey];
        // Totals in department
        $sqlTotal = "SELECT COUNT(*) FROM students WHERE $whereDept";
        $stmt = $conn->prepare($sqlTotal);
        $stmt->execute($params);
        $total_students = (int)$stmt->fetchColumn();

        // Verified in department
        $sqlVer = "SELECT COUNT(*) FROM students WHERE is_verified = 1 AND $whereDept";
        $stmt = $conn->prepare($sqlVer);
        $stmt->execute($params);
        $verified_students = (int)$stmt->fetchColumn();

        // Pending queues (unverified students, research pending approval)
        $sqlUnver = "SELECT COUNT(*) FROM students WHERE is_verified = 0 AND $whereDept";
        $stmt = $conn->prepare($sqlUnver);
        $stmt->execute($params);
        $pending_unverified = (int)$stmt->fetchColumn();

        // Pending research filtered by department
        $paramsRS = [$deptKey];
        $sqlRS = "SELECT COUNT(*) FROM research_submission WHERE COALESCE(status,0) = 0 AND TRIM(LOWER(COALESCE(department,\'\'))) = TRIM(LOWER(?))";
        $stmt = $conn->prepare($sqlRS);
        $stmt->execute($paramsRS);
        $pending_research = (int)$stmt->fetchColumn();

        // Count only for display card
        $dept_student_count = $total_students;
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM students");
        $stmt->execute();
        $total_students = $stmt->fetchColumn();

        $stmt = $conn->prepare("SELECT COUNT(*) FROM students WHERE is_verified = 1");
        $stmt->execute();
        $verified_students = $stmt->fetchColumn();

        // Pending queues (global)
        $stmt = $conn->prepare("SELECT COUNT(*) FROM students WHERE is_verified = 0");
        $stmt->execute();
        $pending_unverified = (int)$stmt->fetchColumn();

        $stmt = $conn->prepare("SELECT COUNT(*) FROM research_submission WHERE COALESCE(status,0) = 0");
        $stmt->execute();
        $pending_research = (int)$stmt->fetchColumn();

        // Global scope summary count
        $dept_student_count = $total_students;
    }

} catch (PDOException $e) {
    $error_message = "Error fetching analytics: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research Adviser Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .permission-disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .permission-disabled:hover {
            transform: none;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-900 min-h-screen flex">
    <!-- Sidebar -->
    <?php include 'subadmin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-4 sm:p-6 md:p-8">
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-800 rounded-lg flex items-center justify-between" id="successAlert">
                <div>
                    <i class="fas fa-check-circle mr-2"></i>
                    <span><?= htmlspecialchars($_SESSION['success']); ?></span>
                </div>
                <button onclick="document.getElementById('successAlert').style.display='none'" class="ml-4 text-green-700 hover:text-green-900">&times;</button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <!-- Header -->
        <header class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-4 sm:p-6 md:p-8 mb-8 border border-gray-200 dark:border-gray-700 relative">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <h1 class="text-3xl sm:text-4xl font-extrabold text-blue-900 dark:text-blue-200 flex items-center">
                    <i class="fas fa-user-tie mr-3"></i>
                    Research Adviser Dashboard
                </h1>

                <!-- Right-aligned date/time + notifications + profile block -->
                <div class="relative w-full mt-3 md:mt-0 md:absolute md:top-4 md:right-4 flex items-center justify-end gap-3 sm:gap-4" id="subProfileDropdown" style="z-index: 9999;">
                    <!-- Day, Date & Time (PH time) -->
                    <div class="text-right hidden sm:block leading-tight">
                        <p class="text-base font-semibold text-blue-900"><?= date('l') ?></p>
                        <p class="text-xs text-gray-600"><?= date('M d, Y') ?></p>
                        <p class="text-xs text-gray-600"><?= date('h:i A') ?></p>
                    </div>
                    <!-- Notifications Bell (Student Activity) -->
                    <div class="relative" id="subNotifDropdown">
                        <?php $notif_total = (int)$unread_student_logs + (int)$pending_unverified + (int)$pending_research; ?>
                        <button class="text-gray-500 hover:text-blue-600 relative" id="subNotifButton" aria-label="Student Activity Notifications">
                            <i class="fas fa-bell text-xl sm:text-2xl"></i>
                            <?php if ($notif_total > 0): ?>
                                <span id="subNotifBadge" class="absolute -top-1 -right-1 bg-red-600 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">
                                    <?= $notif_total > 99 ? '99+' : $notif_total; ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <!-- Notifications Dropdown -->
                        <div id="subNotifMenu" class="hidden fixed sm:absolute top-16 sm:top-auto right-2 left-2 sm:left-auto sm:right-0 mt-2 w-[95vw] sm:w-96 max-h-[70vh] sm:max-h-96 overflow-auto bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-100 dark:border-gray-700 z-50">
                            <div class="px-4 py-2 border-b space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold text-gray-800 dark:text-gray-100">Student Activity</span>
                                    <div class="flex items-center gap-3">
                                        <?php if ($notif_total > 0): ?>
                                            <span class="text-xs text-gray-500"><?= $notif_total; ?> new</span>
                                        <?php endif; ?>
                                        <?php if ($is_admin): ?>
                                            <button id="subClearActivityBtn" class="text-xs text-red-600 hover:underline">Clear</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <!-- Pending Queues quick links -->
                            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                                <div class="grid grid-cols-1 gap-2">
                                    <?php if ($is_admin || $can_verify_students): ?>
                                        <a href="subadmin_verify_students.php" class="flex items-center justify-between px-3 py-2 rounded-md bg-blue-50 hover:bg-blue-100 text-blue-800 dark:bg-blue-900 dark:hover:bg-blue-800 dark:text-blue-100 transition">
                                            <span class="flex items-center gap-2"><i class="fas fa-user-check"></i> Pending Student Verifications</span>
                                            <span class="inline-flex items-center justify-center min-w-[1.75rem] px-2 py-0.5 text-xs font-bold rounded-full bg-blue-600 text-white"><?= (int)$pending_unverified; ?></span>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($is_admin || $can_approve_research): ?>
                                        <a href="subadmin_research_approvals.php" class="flex items-center justify-between px-3 py-2 rounded-md bg-yellow-50 hover:bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:hover:bg-yellow-800 dark:text-yellow-100 transition">
                                            <span class="flex items-center gap-2"><i class="fas fa-clipboard-check"></i> Pending Research Approvals</span>
                                            <span class="inline-flex items-center justify-center min-w-[1.75rem] px-2 py-0.5 text-xs font-bold rounded-full bg-yellow-600 text-white"><?= (int)$pending_research; ?></span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                                <?php if (empty($student_activity_logs)): ?>
                                    <li class="p-4 text-gray-500 dark:text-gray-400">No recent activity.</li>
                                <?php else: ?>
                                    <?php foreach ($student_activity_logs as $log): ?>
                                        <?php 
                                            $label = ucfirst(str_replace('_',' ', $log['action']));
                                            switch ($log['action']) { case 'upload_research': $label = 'Uploaded Research'; break; case 'edit_research': $label = 'Edited Research'; break; case 'delete_research': $label = 'Deleted Research'; break; case 'login': $label = 'Logged In'; break; case 'logout': $label = 'Logged Out'; break; case 'bookmark_research': $label = 'Bookmarked Research'; break; }
                                            $ts = date('M d, Y h:i A', strtotime($log['created_at']));
                                            $name = trim(($log['firstname'] ?? '') . ' ' . ($log['lastname'] ?? ''));
                                            $strandDisp = $log['strand'] ?? '';
                                            // Build details preview from JSON
                                            $detailPreview = '';
                                            if (!empty($log['details'])) {
                                                $decoded = json_decode($log['details'], true);
                                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                                    $keys = ['title','strand','year','group_number','section','document'];
                                                    $parts = [];
                                                    foreach ($keys as $k) {
                                                        if (isset($decoded[$k]) && $decoded[$k] !== '') {
                                                            $parts[] = '<span class=\'mr-3\'><span class=\'font-medium\'>'.htmlspecialchars($k).':</span> '.htmlspecialchars(is_scalar($decoded[$k]) ? (string)$decoded[$k] : json_encode($decoded[$k])).'</span>';
                                                        }
                                                    }
                                                    if (!empty($parts)) { $detailPreview = '<div class=\'mt-1 text-[11px] text-gray-600 dark:text-gray-300\'>'.implode('', $parts).'</div>'; }
                                                }
                                            }
                                        ?>
                                        <li class="p-3 flex items-start gap-3">
                                            <div class="mt-1 text-blue-600 dark:text-blue-400"><i class="fas fa-user-graduate"></i></div>
                                            <div class="flex-1">
                                                <div class="flex items-center justify-between">
                                                    <div class="pr-3 whitespace-normal break-words min-w-0">
                                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate"><?= htmlspecialchars($label) ?></p>
                                                        <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400 flex items-center flex-wrap gap-1">
                                                            <?php if ($name !== ''): ?>
                                                                <span class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300"><?= htmlspecialchars($name) ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($strandDisp !== ''): ?>
                                                                <span class="px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 dark:bg-blue-900 dark:text-blue-200"><?= htmlspecialchars($strandDisp) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?= $detailPreview ?>
                                                    </div>
                                                    <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300"><?= htmlspecialchars($ts) ?></span>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="text-right hidden sm:block">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Welcome back,</p>
                        <p class="text-sm sm:text-base font-semibold text-gray-800 dark:text-gray-100 truncate max-w-[50vw] sm:max-w-none"><?= htmlspecialchars($_SESSION['fullname']) ?></p>
                    </div>
                    <button type="button" id="subProfileBtn" class="flex items-center space-x-2 sm:space-x-3 focus:outline-none">
                        <div class="relative">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center overflow-hidden border border-gray-200">
                                <?php if (!empty($profile_pic) || !empty($_SESSION['sub_profile_pic'])): ?>
                                    <img src="images/<?= htmlspecialchars(!empty($profile_pic) ? $profile_pic : $_SESSION['sub_profile_pic']) ?>" alt="Profile" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fas fa-user-shield text-gray-500"></i>
                                <?php endif; ?>
                            </div>
                            <span class="absolute bottom-0 right-0 block w-3 h-3 bg-green-500 rounded-full border-2 border-white"></span>
                        </div>
                        <i class="fas fa-chevron-down text-gray-400 text-sm"></i>
                    </button>

                    <!-- Dropdown Menu -->
                    <div id="subProfileDropdownMenu" class="hidden absolute right-0 top-full mt-2 w-64 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50">
                        <div class="p-4 border-b border-gray-100 dark:border-gray-700">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center overflow-hidden border border-gray-200">
                                    <?php if (!empty($profile_pic) || !empty($_SESSION['sub_profile_pic'])): ?>
                                        <img src="images/<?= htmlspecialchars(!empty($profile_pic) ? $profile_pic : $_SESSION['sub_profile_pic']) ?>" alt="Profile" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <i class="fas fa-user-shield text-gray-500"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($_SESSION['fullname']) ?></p>
                                    <p class="text-xs text-blue-600">Sub-Admin</p>
                                </div>
                            </div>
                        </div>
                        <div class="py-2">
                            <button onclick="openSubadminProfile()" class="w-full flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 text-left">
                                <i class="fas fa-edit mr-3 text-gray-400"></i>
                                Edit Profile
                            </button>
                            <a href="logout.php" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i class="fas fa-sign-out-alt mr-3 text-gray-400"></i>
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

    


        <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
            <!-- Total Students -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 flex justify-between items-center card-hover">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">TOTAL STUDENTS</p>
                    <p class="text-3xl font-bold text-blue-600 dark:text-blue-400"><?= $total_students ?></p>
                </div>
                <i class="fas fa-users text-4xl text-blue-500 dark:text-blue-400"></i>
            </div>

            <!-- Verified Students -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 flex justify-between items-center card-hover">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">VERIFIED</p>
                    <p class="text-3xl font-bold text-green-600 dark:text-green-400"><?= $verified_students ?></p>
                </div>
                <i class="fas fa-user-check text-4xl text-green-500 dark:text-green-400"></i>
            </div>

            <!-- Total Submissions -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 flex justify-between items-center card-hover">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">TOTAL SUBMISSIONS</p>
                    <p class="text-3xl font-bold text-purple-600 dark:text-purple-400"><?= $total_submissions ?></p>
                </div>
                <i class="fas fa-file-alt text-4xl text-purple-500 dark:text-purple-400"></i>
            </div>

            <!-- Approved Research -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 flex justify-between items-center card-hover">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">APPROVED</p>
                    <p class="text-3xl font-bold text-orange-600 dark:text-orange-400"><?= $research_stats['approved'] ?></p>
                </div>
                <div class="bg-orange-500 p-2 rounded-full">
                    <i class="fas fa-check text-2xl text-white"></i>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-8 mb-8">
            <!-- Research Status Chart -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6 border border-gray-200 dark:border-gray-700 flex flex-col items-center justify-center min-h-[350px]">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Research Status</h2>
                <div class="w-full flex justify-center">
                    <canvas id="researchStatusChart" height="220" style="max-width:320px;"></canvas>
                </div>
            </div>

            <!-- Students in My Department (Bar Chart) -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6 border border-gray-200 dark:border-gray-700 flex flex-col items-center justify-center min-h-[350px]">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Students in My Department</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Department: <span class="font-semibold text-blue-700 dark:text-blue-300"><?= htmlspecialchars($my_department ?: 'All') ?></span></p>
                <div class="w-full flex justify-center">
                    <canvas id="studentsDeptChart" height="220" style="max-width:420px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Research Submissions removed per request -->
        </div>

        <!-- Student Activity Logs moved into notifications bell -->
    </main>

    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            const isDarkMode = document.documentElement.classList.contains('dark');
            const legendColor = isDarkMode ? '#E5E7EB' : '#1F2937'; // gray-200 for dark, gray-800 for light

            // Research Status Chart
            const researchStatusCtx = document.getElementById('researchStatusChart').getContext('2d');
            const researchStatusChart = new Chart(researchStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Approved', 'Pending'],
                    datasets: [{
                        data: [<?= $research_stats['approved'] ?>, <?= $research_stats['pending'] ?>],
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.7)', // green-500
                            'rgba(234, 179, 8, 0.7)'  // yellow-500
                        ],
                        borderColor: [
                            'rgba(16, 185, 129, 1)',
                            'rgba(234, 179, 8, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: legendColor
                            }
                        }
                    }
                }
            });

            // Students in My Department - Bar Chart
            const studentsDeptCtx = document.getElementById('studentsDeptChart').getContext('2d');
            const studentsDeptChart = new Chart(studentsDeptCtx, {
                type: 'bar',
                data: {
                    labels: ['Total', 'Verified', 'Unverified'],
                    datasets: [{
                        label: 'Students',
                        data: [<?= (int)$dept_student_count ?>, <?= (int)$verified_students ?>, <?= max(0, (int)$total_students - (int)$verified_students) ?>],
                        backgroundColor: [
                            'rgba(59, 130, 246, 0.7)',  // blue
                            'rgba(16, 185, 129, 0.7)',  // green
                            'rgba(234, 179, 8, 0.7)'    // yellow
                        ],
                        borderColor: [
                            'rgba(59, 130, 246, 1)',
                            'rgba(16, 185, 129, 1)',
                            'rgba(234, 179, 8, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, ticks: { color: legendColor } },
                        x: { ticks: { color: legendColor } }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });

            // Notifications dropdown (Student Activity)
            (function(){
                const notifBtn = document.getElementById('subNotifButton');
                const notifMenu = document.getElementById('subNotifMenu');
                const notifDropdown = document.getElementById('subNotifDropdown');
                const badge = document.getElementById('subNotifBadge');
                const clearBtn = document.getElementById('subClearActivityBtn');
                if (notifBtn && notifMenu) {
                    notifBtn.addEventListener('click', async function(e){
                        e.preventDefault();
                        e.stopPropagation();
                        notifMenu.classList.toggle('hidden');
                        // Close profile dropdown if open
                        const profileMenuEl = document.getElementById('subProfileDropdownMenu');
                        if (profileMenuEl && !profileMenuEl.classList.contains('hidden')) {
                            profileMenuEl.classList.add('hidden');
                            profileMenuEl.style.display = 'none';
                        }
                        // On first open, optimistically remove badge and persist viewed
                        if (!window._subMarkedLogs && !notifMenu.classList.contains('hidden')) {
                            window._subMarkedLogs = true;
                            if (badge) badge.remove();
                            try { await fetch('include/mark_logs_viewed.php', { credentials: 'same-origin' }); } catch(_) {}
                        }
                    });
                    // Close when clicking outside
                    document.addEventListener('click', function(e){
                        if (notifDropdown && !notifDropdown.contains(e.target)) {
                            notifMenu.classList.add('hidden');
                        }
                    });
                    // Close on Escape
                    document.addEventListener('keydown', function(e){
                        if (e.key === 'Escape') notifMenu.classList.add('hidden');
                    });
                }
                if (clearBtn) {
                    clearBtn.addEventListener('click', async function(e){
                        e.preventDefault();
                        const proceed = await (async () => {
                            if (typeof Swal === 'undefined') return confirm('Clear all activity logs? This cannot be undone.');
                            const res = await Swal.fire({
                                title: 'Clear all activity logs?',
                                text: 'This action cannot be undone.',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#d33',
                                cancelButtonColor: '#6b7280',
                                confirmButtonText: 'Yes, clear all',
                                cancelButtonText: 'Cancel'
                            });
                            return res.isConfirmed;
                        })();
                        if (!proceed) return;
                        try {
                            const res = await fetch('include/clear_activity_logs.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, credentials: 'same-origin', body: 'confirm=1' });
                            const data = await res.json().catch(()=>({ ok: false }));
                            if (!res.ok || !data.ok) throw new Error('Failed');
                            const list = document.querySelector('#subNotifMenu ul.divide-y');
                            if (list) list.innerHTML = '<li class="p-4 text-gray-500 dark:text-gray-400">No recent activity.</li>';
                            if (badge) badge.remove();
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({ icon: 'success', title: 'Activity logs cleared', toast: true, position: 'top-end', timer: 1500, showConfirmButton: false });
                            }
                        } catch (_) {}
                    });
                }
            })();

            // Profile Dropdown Toggle (Sub-Admin)
            const subProfileDropdown = document.getElementById('subProfileDropdown'); // container
            const subProfileDropdownMenu = document.getElementById('subProfileDropdownMenu');
            const subProfileButtons = document.querySelectorAll('#subProfileBtn');
            if (subProfileButtons.length && subProfileDropdownMenu && subProfileDropdown) {
                subProfileButtons.forEach((btn) => {
                    btn.style.cursor = 'pointer';
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        // Close notifications if open to avoid overlap
                        const notifMenu = document.getElementById('subNotifMenu');
                        if (notifMenu && !notifMenu.classList.contains('hidden')) notifMenu.classList.add('hidden');
                        subProfileDropdownMenu.classList.toggle('hidden');
                        subProfileDropdownMenu.style.display = subProfileDropdownMenu.classList.contains('hidden') ? 'none' : 'block';
                    });
                });

                // Prevent clicks inside the menu from bubbling and allow links to work
                subProfileDropdownMenu.addEventListener('click', function(e) {
                    e.stopPropagation();
                });

                document.addEventListener('click', function(e) {
                    if (!subProfileDropdown.contains(e.target) && !subProfileDropdownMenu.contains(e.target)) {
                        subProfileDropdownMenu.classList.add('hidden');
                        subProfileDropdownMenu.style.display = 'none';
                    }
                });
            }

            // Direct bind SweetAlert2 to dropdown Sign Out (fallback to ensure it always shows)
            const dropdownLogout = document.querySelector('#subProfileDropdownMenu a[href="logout.php"]');
            if (dropdownLogout) {
                dropdownLogout.addEventListener('click', function(e) {
                    e.preventDefault();
                    const href = this.getAttribute('href');
                    if (typeof Swal === 'undefined') {
                        if (confirm('Are you sure you want to sign out?')) {
                            window.location.href = href;
                        }
                        return;
                    }
                    Swal.fire({
                        title: 'Sign out?',
                        text: 'Are you sure you want to sign out?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Yes, sign out',
                        cancelButtonText: 'Cancel',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = href;
                        }
                    });
                });
            }
        });
    </script>
</body>
    <style>
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .permission-disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .permission-disabled:hover {
            transform: none;
        }
    </style>

    <!-- Sub-Admin Edit Profile Modal -->
    <div id="subadminProfileModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-2 sm:px-4">
            <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeSubadminProfile()"></div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-lg p-4 sm:p-6 relative mx-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Edit Profile</h3>
                    <button onclick="closeSubadminProfile()" class="text-gray-500 hover:text-gray-700">&times;</button>
                </div>
                <form action="update_subadmin.php" method="POST" id="subadminProfileForm" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 gap-3">
                        <!-- Current Profile Picture + Upload -->
                        <div>
                            <label class="text-sm text-gray-600 flex items-center gap-2"><i class="fas fa-image"></i> Profile Picture</label>
                            <div class="flex items-center gap-3 mt-1">
                                <div class="w-14 h-14 rounded-full overflow-hidden bg-gray-100 border">
                                    <?php if (!empty($_SESSION['sub_profile_pic'])): ?>
                                        <img src="images/<?= htmlspecialchars($_SESSION['sub_profile_pic']) ?>" alt="Profile" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-gray-400"><i class="fas fa-user"></i></div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1">
                                    <input type="file" name="profile_pic" accept="image/*" class="w-full px-2 py-1 border rounded-md">
                                    <?php if (!empty($_SESSION['sub_profile_pic'])): ?>
                                    <label class="inline-flex items-center mt-1 text-sm">
                                        <input type="checkbox" name="remove_profile_pic" value="1" class="mr-2">
                                        Remove current picture
                                    </label>
                                    <?php endif; ?>
                                    <p class="text-xs text-gray-500">Allowed: JPG, PNG, WEBP</p>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="text-sm text-gray-600">Full Name</label>
                            <input type="text" name="fullname" value="<?= htmlspecialchars($_SESSION['fullname']) ?>" class="w-full px-3 py-2 border rounded-md" required>
                        </div>

                        <!-- Current Password with toggle -->
                        <div>
                            <label class="text-sm text-gray-600">Current Password (required to change password)</label>
                            <div class="relative">
                                <input type="password" name="current_password" id="sub_current_password" class="w-full px-3 py-2 border rounded-md pr-10" placeholder="Leave blank if not changing password">
                                <button type="button" class="absolute inset-y-0 right-2 flex items-center text-gray-500" onclick="togglePassword('sub_current_password','sub_current_toggle')" aria-label="Toggle current password">
                                    <i id="sub_current_toggle" class="fas fa-eye-slash"></i>
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-sm text-gray-600">New Password</label>
                                <div class="relative">
                                    <input type="password" name="new_password" id="sub_new_password" class="w-full px-3 py-2 border rounded-md pr-10" placeholder="New password">
                                    <button type="button" class="absolute inset-y-0 right-2 flex items-center text-gray-500" onclick="togglePassword('sub_new_password','sub_new_toggle')" aria-label="Toggle new password">
                                        <i id="sub_new_toggle" class="fas fa-eye-slash"></i>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="text-sm text-gray-600">Confirm New Password</label>
                                <div class="relative">
                                    <input type="password" name="confirm_password" id="sub_confirm_password" class="w-full px-3 py-2 border rounded-md pr-10" placeholder="Confirm new password">
                                    <button type="button" class="absolute inset-y-0 right-2 flex items-center text-gray-500" onclick="togglePassword('sub_confirm_password','sub_confirm_toggle')" aria-label="Toggle confirm password">
                                        <i id="sub_confirm_toggle" class="fas fa-eye-slash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end space-x-2">
                        <button type="button" onclick="closeSubadminProfile()" class="px-4 py-2 bg-gray-200 rounded-md">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openSubadminProfile() {
            document.getElementById('subadminProfileModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }
        function closeSubadminProfile() {
            document.getElementById('subadminProfileModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        // Toggle password visibility for a given input and icon
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (!input || !icon) return;
            if (input.getAttribute('type') === 'password') {
                input.setAttribute('type', 'text');
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                input.setAttribute('type', 'password');
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        }

        // Basic client-side validation for password match and length
        document.getElementById('subadminProfileForm').addEventListener('submit', function(e) {
            const newPwd = document.getElementById('sub_new_password').value;
            const confirm = document.getElementById('sub_confirm_password').value;
            if (newPwd || confirm) {
                if (newPwd !== confirm) {
                    e.preventDefault();
                    alert('New passwords do not match.');
                    return;
                }
                if (newPwd && newPwd.length < 8) {
                    e.preventDefault();
                    alert('New password must be at least 8 characters long.');
                    return;
                }
            }
        });
    </script>