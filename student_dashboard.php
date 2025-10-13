<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php'; // Database connection
// Ensure PH time for all date()/time() usage on this page
date_default_timezone_set('Asia/Manila');

// Safely retrieve session data or provide default values
$firstname = isset($_SESSION['firstname']) ? $_SESSION['firstname'] : 'Student';
$middlename = isset($_SESSION['middlename']) ? $_SESSION['middlename'] : '';
$lastname = isset($_SESSION['lastname']) ? $_SESSION['lastname'] : 'Student';
$email = isset($_SESSION['email']) ? $_SESSION['email'] : 'Not Available';
$profile_pic = isset($_SESSION['profile_pic']) ? 'images/' . $_SESSION['profile_pic'] : 'images/default.jpg';
$department = $_SESSION['department'] ?? ($_SESSION['strand'] ?? '');
$strand = $department; // temporary compatibility
$student_role = $_SESSION['student_role'] ?? 'Member';

// Ensure the profile picture file exists
if (!file_exists($profile_pic) || empty($_SESSION['profile_pic'])) {
    $profile_pic = 'images/default.jpg';
}

// Fetch research submissions
$stmt = $conn->prepare("SELECT * FROM research_submission WHERE student_id = ?");
$stmt->execute([$_SESSION['student_id']]);
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Submission counters for stats
$submissionCount = is_array($submissions) ? count($submissions) : 0;
$approvedCount = 0;
foreach ($submissions as $s) {
    if (!empty($s['status']) && (int)$s['status'] === 1) { $approvedCount++; }
}
$pendingCount = max(0, $submissionCount - $approvedCount);

// Determine which column announcements table uses for targeting (department vs legacy strand)
try {
    $colCheck = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements' AND COLUMN_NAME = 'department'");
    $colCheck->execute();
    $annTargetCol = ((int)$colCheck->fetchColumn() > 0) ? 'department' : 'strand';
} catch (Exception $e) { $annTargetCol = 'strand'; }

// Fetch announcements targeted to student's department or all
$query = "SELECT * FROM announcements WHERE (" . $annTargetCol . " = ? OR " . $annTargetCol . " IS NULL OR " . $annTargetCol . " = '') ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute([$department]);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Unread announcements count for badge
// Ensure read-tracking table exists (first deploy safety)
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS student_announcement_reads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        announcement_id INT NOT NULL,
        read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_student_announcement (student_id, announcement_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // fail silently; we will treat as zero unread if creation fails
}

$unreadSql = "SELECT COUNT(*) FROM announcements a 
      WHERE (a." . $annTargetCol . " = ? OR a." . $annTargetCol . " IS NULL OR a." . $annTargetCol . " = '')
        AND NOT EXISTS (
            SELECT 1 FROM student_announcement_reads r
            WHERE r.announcement_id = a.id AND r.student_id = ?
        )";
$unreadStmt = $conn->prepare($unreadSql);
$unreadStmt->execute([$department, (int)$_SESSION['student_id']]);
$announcementUnreadCount = (int)$unreadStmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | SRC Research Repository</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom styles -->
    <style>
        .sidebar-transition {
            transition: all 0.3s ease;
        }
        .dropdown-menu {
            animation: slideDown 0.3s ease forwards;
            opacity: 0;
            transform: translateY(-10px);
        }
        @keyframes slideDown {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .countdown-glow {
            text-shadow: 0 0 8px rgba(59, 130, 246, 0.3);
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-approved {
            background-color: #dcfce7;
            color: #166534;
        }
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .action-btn {
            transition: all 0.2s ease;
        }
        .action-btn:hover {
            transform: translateY(-1px);
        }
        /* Subtle background pattern */
        .bg-pattern {
            background-image: radial-gradient(circle at 1px 1px, rgba(30, 64, 175, 0.06) 1px, transparent 1px);
            background-size: 24px 24px;
        }
        /* Glass card effect */
        .glass {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: saturate(140%) blur(6px);
            -webkit-backdrop-filter: saturate(140%) blur(6px);
            border: 1px solid rgba(0,0,0,0.06);
        }
        /* Smooth scroll */
        html { scroll-behavior: smooth; }
    </style>
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

    <!-- Main Content -->
    <div class="flex-1 lg:ml-0 w-full">
        <!-- Top Header with Gmail-style Profile -->
        <header class="bg-white shadow-sm border-b border-gray-200 px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900"></h1>
                    <div class="text-right sm:text-left leading-tight">
                        <p class="text-sm sm:text-base font-semibold text-blue-900"><?= date('l') ?></p>
                        <p class="text-xs sm:text-sm text-gray-600"><?= date('M d, Y') ?></p>
                        <p class="text-xs sm:text-sm text-gray-600"><?= date('h:i A') ?></p>
                    </div>
                </div>
                
                <!-- Notification + Profile Section -->
                <div id="topUserControls" class="flex items-center space-x-4 relative" style="z-index: 9999;">
                    <!-- Notifications Bell -->
                    <?php $announcementCount = is_array($announcements) ? count($announcements) : 0; ?>
                    <div id="notifContainer" class="relative">
                        <button type="button" id="notifBellBtn" class="relative p-2 rounded-full hover:bg-gray-100 transition-colors duration-200 focus:outline-none">
                            <i class="fas fa-bell text-gray-600 text-xl"></i>
                            <?php if ($announcementUnreadCount > 0): ?>
                                <span class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold leading-none text-white bg-red-600 rounded-full">
                                    <?php echo $announcementUnreadCount; ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <!-- Notifications Dropdown -->
                        <div id="notifMenu" class="hidden fixed sm:absolute top-16 sm:top-auto sm:right-0 right-2 left-2 sm:left-auto mt-2 w-[95vw] sm:w-80 md:w-96 max-w-[95vw] bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden z-50">
                            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                                <p class="font-semibold text-gray-800">Announcements</p>
                                <?php if ($announcementUnreadCount > 0): ?>
                                    <span class="text-xs text-gray-500"><?php echo $announcementUnreadCount; ?> new</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($announcementCount > 0): ?>
                                <ul class="max-h-[70vh] sm:max-h-80 overflow-auto divide-y divide-gray-100">
                                    <?php foreach ($announcements as $a): 
                                        $isPast = (new DateTime($a['deadline'])) < (new DateTime());
                                    ?>
                                        <li class="p-2 sm:p-3 hover:bg-gray-50">
                                            <div class="flex items-start justify-between">
                                                <div class="pr-3 whitespace-normal break-words min-w-0">
                                                    <p class="text-sm sm:text-base font-medium text-gray-900 truncate"><?php echo htmlspecialchars($a['title']); ?></p>
                                                    <p class="hidden sm:block mt-1 text-xs text-gray-600"><?php echo htmlspecialchars(mb_strimwidth(strip_tags($a['content']), 0, 100, '…')); ?></p>
                                                    <div class="mt-1 text-[10px] sm:text-[11px] text-gray-500">
                                                        Deadline: <?php echo date('M j, Y g:i A', strtotime($a['deadline'])); ?>
                                                    </div>
                                                </div>
                                                <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] sm:text-xs font-medium <?php echo $isPast ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
                                                    <?php echo $isPast ? 'Closed' : 'Active'; ?>
                                                </span>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <a href="student_announcements.php" class="block text-center text-xs sm:text-sm text-blue-primary hover:text-blue-secondary py-2">
                                    View all announcements
                                </a>
                            <?php else: ?>
                                <div class="p-6 text-center text-gray-500">
                                    <i class="fas fa-bell-slash text-2xl mb-2 text-gray-300"></i>
                                    <p class="text-sm">No announcements at the moment.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Gmail-style Profile -->
                    <div id="profileDropdownContainer" class="relative pointer-events-auto">
                        <button type="button" id="profileDropdown" class="flex items-center space-x-3 bg-gray-50 hover:bg-gray-100 rounded-full p-2 transition-colors duration-200">
                            <div class="relative">
                                <img src="<?php echo htmlspecialchars($profile_pic); ?>" 
                                     class="w-10 h-10 rounded-full object-cover border-2 border-gray-200" 
                                     alt="Profile Picture">
                                <div class="absolute -bottom-0.5 -right-0.5 bg-green-500 w-3 h-3 rounded-full border-2 border-white"></div>
                            </div>
                            <div class="hidden sm:block text-left">
                                <p class="text-sm font-medium text-gray-900 flex items-center gap-2">
                                    <?php echo htmlspecialchars($firstname . ' ' . $lastname); ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    Department: <?php echo htmlspecialchars($department); ?>
                                </p>
                            </div>
                            <i class="fas fa-chevron-down text-gray-400 text-sm"></i>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div id="profileDropdownMenu" class="hidden absolute right-0 mt-2 w-[92vw] sm:w-72 max-w-[92vw] sm:max-w-[18rem] bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                            <div class="p-4 border-b border-gray-100">
                                <div class="flex items-center space-x-3">
                                    <img src="<?php echo htmlspecialchars($profile_pic); ?>" 
                                         class="w-12 h-12 rounded-full object-cover" 
                                         alt="Profile Picture">
                                    <div class="min-w-0 max-w-full">
                                        <p class="font-medium text-gray-900 flex items-center gap-2 break-words">
                                            <?php echo htmlspecialchars($firstname . ' ' . $lastname); ?>
                                        </p>
                                        <p class="text-sm text-gray-500 break-words leading-snug max-w-full"><?php echo htmlspecialchars($email); ?></p>
                                        <p class="text-xs text-blue-600 break-words leading-snug max-w-full">Department: <?php echo htmlspecialchars($department); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="py-2">
                                <button onclick="toggleModal()" class="w-full flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 text-left">
                                    <i class="fas fa-edit mr-3 text-gray-400"></i>
                                    Edit Profile
                                </button>
                                <a href="logout.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-sign-out-alt mr-3 text-gray-400"></i>
                                    Sign Out
                                </a>
                            </div>
                        </div>
                    </div>
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
                        text: <?php echo json_encode(preg_replace('/^Error updating profile:\\s*/i','', $_SESSION['error'])); ?>,
                        confirmButtonText: 'OK'
                    });
                });
            </script>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Main Content Area -->
        <div class="p-4 sm:p-6 lg:p-8 space-y-6 lg:space-y-8">
            <!-- Overlay for mobile (single, used by sidebar) -->
            <div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden"></div>
          <!-- Welcome Section -->
          <section id="welcome" class="bg-gradient-to-r from-blue-primary to-blue-secondary text-white p-6 sm:p-8 rounded-xl shadow-lg card-hover transition-all duration-300">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between">
                <div class="mb-4 sm:mb-0">
                    <h2 class="text-2xl sm:text-3xl font-bold mb-2">Welcome, <?php echo htmlspecialchars($firstname); ?>!</h2>
                    <p class="text-blue-100 text-sm sm:text-base max-w-2xl">
                        Welcome to the <span class="font-semibold">Online Research Repository</span><span class="font-semibold"> at Santa Rita College of Pampanga</span>.
                    </p>
                    <p class="text-blue-100 text-sm sm:text-base mt-2">
                        Upload your research projects and manage your submissions in one place.
                    </p>
                </div>
                <div class="flex-shrink-0">
                    <div class="bg-white bg-opacity-20 rounded-full p-3 inline-flex">
                        <i class="fas fa-graduation-cap text-4xl text-white"></i>
                    </div>
                </div>
            </div>
        </section>
        <!-- Edit Profile Modal -->
        <div id="editProfileModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center">
                <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="toggleModal()"></div>
                
                <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-2xl">
                    <div class="flex justify-between items-center mb-5 border-b pb-4">
                        <h3 class="text-lg font-bold text-gray-800">Edit Profile</h3>
                        <button type="button" class="text-gray-400 hover:text-gray-600" onclick="toggleModal()">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <form action="update_profile.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Profile Picture</label>
                                <div class="flex items-center space-x-3">
                                    <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Current" class="w-12 h-12 rounded-full object-cover">
                                    <input type="file" name="profile_pic" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-lg file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-primary hover:file:bg-blue-100 transition-all duration-200">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                <input type="text" name="firstname" value="<?php echo htmlspecialchars($firstname); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-primary focus:border-transparent transition-all duration-200" required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                                <input type="text" name="middlename" value="<?php echo htmlspecialchars($middlename); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-primary focus:border-transparent transition-all duration-200">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                <input type="text" name="lastname" value="<?php echo htmlspecialchars($lastname); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-primary focus:border-transparent transition-all duration-200" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-primary focus:border-transparent transition-all duration-200" required>
                            </div>
                            
                            <!-- Strand is fixed and not editable by students -->
                            
                            
                        </div>

                        <!-- Password Change Section -->
                        <div class="pt-4 mt-4 border-t border-gray-200">
                            <h4 class="text-sm font-medium text-gray-700 mb-3 flex items-center">
                                <i class="fas fa-lock mr-2"></i>
                                Change Password
                            </h4>
                            
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                                    <div class="relative">
                                        <input type="text" name="current_password" id="current_password" class="w-full px-3 py-2 border border-gray-300 rounded-lg pr-10 focus:ring-2 focus:ring-blue-primary focus:border-transparent transition-all duration-200">
                                        <button type="button" onclick="togglePassword('current_password')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                            <i class="fas fa-eye" id="current_password_icon"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                                    <div class="relative">
                                        <input type="text" name="new_password" id="new_password" class="w-full px-3 py-2 border border-gray-300 rounded-lg pr-10 focus:ring-2 focus:ring-blue-primary focus:border-transparent transition-all duration-200">
                                        <button type="button" onclick="togglePassword('new_password')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                            <i class="fas fa-eye" id="new_password_icon"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                                    <div class="relative">
                                        <input type="text" name="confirm_password" id="confirm_password" class="w-full px-3 py-2 border border-gray-300 rounded-lg pr-10 focus:ring-2 focus:ring-blue-primary focus:border-transparent transition-all duration-200">
                                        <button type="button" onclick="togglePassword('confirm_password')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                            <i class="fas fa-eye" id="confirm_password_icon"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3 pt-2">
                            <button type="button" onclick="toggleModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 bg-blue-primary text-white rounded-lg hover:bg-blue-secondary transition-colors duration-200 flex items-center">
                                <i class="fas fa-save mr-2"></i>
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
       

       

      
        <!-- Upload Research Section (visible to all students) -->
        <section id="upload-research" class="bg-white p-5 sm:p-6 rounded-xl shadow-lg card-hover transition-all duration-300">
            <div class="flex items-center mb-5">
                <i class="fas fa-cloud-upload-alt text-2xl text-blue-primary mr-3"></i>
                <h3 class="text-xl font-bold text-gray-800">Upload Research</h3>
            </div>

            <?php
            // Set timezone to Philippines
            date_default_timezone_set('Asia/Manila');
            // Ensure open_at column exists for gating upload windows
            try {
                $checkOpen = $conn->prepare("SHOW COLUMNS FROM announcements LIKE 'open_at'");
                $checkOpen->execute();
                if ($checkOpen->rowCount() == 0) {
                    $conn->exec("ALTER TABLE announcements ADD COLUMN open_at DATETIME NULL AFTER content");
                }
            } catch (Exception $e) { /* ignore */ }

            // Active: open_at <= now and deadline > now
            $query = "SELECT * FROM announcements WHERE (" . $annTargetCol . " = ? OR " . $annTargetCol . " IS NULL OR " . $annTargetCol . " = '') AND COALESCE(open_at, created_at, NOW()) <= NOW() AND deadline > NOW() ORDER BY deadline ASC LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->execute([$department]);
            $activeAnnouncement = $stmt->fetch(PDO::FETCH_ASSOC);

            // Upcoming: next item where open_at > now
            $upcomingAnnouncement = null;
            if (!$activeAnnouncement) {
                $q2 = "SELECT * FROM announcements WHERE (" . $annTargetCol . " = ? OR " . $annTargetCol . " IS NULL OR " . $annTargetCol . " = '') AND COALESCE(open_at, created_at, NOW()) > NOW() ORDER BY COALESCE(open_at, created_at) ASC LIMIT 1";
                $st2 = $conn->prepare($q2);
                $st2->execute([$department]);
                $upcomingAnnouncement = $st2->fetch(PDO::FETCH_ASSOC);
            }

            // Check if student has already submitted a document
            $stmt = $conn->prepare("SELECT COUNT(*) as submission_count FROM research_submission WHERE student_id = ?");
            $stmt->execute([$_SESSION['student_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $hasSubmitted = $result['submission_count'] > 0;
            ?>

            <?php 
            if ($activeAnnouncement) {
                $deadline = new DateTime($activeAnnouncement['deadline']);
                $openAtDt = !empty($activeAnnouncement['open_at']) ? new DateTime($activeAnnouncement['open_at']) : null;
                $now = new DateTime();
                $interval = $now->diff($deadline);
                $totalHours = ($interval->days * 24) + $interval->h;
                $totalMinutes = $totalHours * 60 + $interval->i;
                $timeRemaining = '';
                if ($interval->days > 0) {
                    $timeRemaining .= $interval->days . ' day' . ($interval->days > 1 ? 's' : '') . ', ';
                }
                if ($interval->h > 0 || $interval->days > 0) {
                    $timeRemaining .= $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ', ';
                }
                $timeRemaining .= $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
                $deadlineTimestamp = strtotime($activeAnnouncement['deadline']) * 1000;
                ?>
                
                <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-100">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-3">
                        <h4 class="font-bold text-blue-primary flex items-center">
                            <i class="fas fa-calendar-check mr-2"></i>
                            Active Submission Period
                        </h4>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mt-2 sm:mt-0">
                            <i class="fas fa-check-circle mr-1.5"></i>
                            Open
                        </span>
                    </div>
                    
                    <p class="text-gray-700 font-medium"><?php echo htmlspecialchars($activeAnnouncement['title']); ?></p>
                    
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-calendar-alt mr-2 text-blue-500"></i>
                            <span>Deadline: <?php echo date('M j, Y', strtotime($activeAnnouncement['deadline'])); ?></span>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-clock mr-2 text-blue-500"></i>
                            <span><?php echo date('g:i A', strtotime($activeAnnouncement['deadline'])); ?></span>
                        </div>
                        <?php if ($openAtDt): ?>
                        <div class="flex items-center text-sm text-gray-600 sm:col-span-2">
                            <i class="fas fa-door-open mr-2 text-blue-500"></i>
                            <span>Opened: <?php echo date('M j, Y g:i A', strtotime($activeAnnouncement['open_at'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-4">
                        <p class="text-sm font-medium text-blue-700 mb-1 flex items-center">
                            <i class="fas fa-hourglass-half mr-2"></i>
                            Time Remaining
                        </p>
                        <div id="countdown" class="text-lg font-bold text-blue-800 countdown-glow">
                            <?php echo $timeRemaining; ?>
                        </div>
                    </div>
                </div>

                <?php 
                if ($hasSubmitted) { 
                    ?>
                    <div class="text-center py-10">
                        <div class="mx-auto w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl text-yellow-500"></i>
                        </div>
                        <div class="text-yellow-600 text-lg font-semibold mb-2">Document Already Submitted</div>
                        <p class="text-gray-600 max-w-md mx-auto">
                            You have already submitted a research document. Multiple submissions are not allowed. If you need to make changes, please edit or delete your existing submission.
                        </p>
                    </div>
                    <?php 
                } else { 
                    ?>
                    <form id="uploadForm" action="upload_research.php" method="POST" enctype="multipart/form-data" class="space-y-5">
                        <input type="hidden" name="student_id" value="<?php echo $_SESSION['student_id']; ?>">
                        <input type="hidden" name="department" value="<?php echo htmlspecialchars($department); ?>">

                        <!-- Header like the provided mock -->
                        <div class="-mt-2 -mx-2 px-2 py-1">
                            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-50 text-blue-600"><i class="fas fa-cloud-upload-alt"></i></span>
                                <span>Upload <span class="underline">Research</span></span>
                            </h3>
                        </div>

                        <!-- Row 1: Title + Academic Year -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Research Title <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fas fa-book"></i></span>
                                    <input type="text" name="title" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-primary focus:border-blue-primary transition-all placeholder-gray-400" placeholder="Enter research title" required>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Academic Year <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fas fa-calendar"></i></span>
                                    <?php
                                        // Compute real-time School Year with June (6) cutoff
                                        // If month >= 6 (June-Dec) => SY is currentYear-currentYear+1
                                        // If month <= 5 (Jan-May) => SY is (currentYear-1)-currentYear
                                        $nowYear = (int)date('Y');
                                        $nowMonth = (int)date('n');
                                        $startYear = ($nowMonth >= 6) ? $nowYear : ($nowYear - 1);
                                        $computedSY = 'A.Y. ' . $startYear . '-' . ($startYear + 1);
                                    ?>
                                    <input type="text" value="<?php echo htmlspecialchars($computedSY); ?>" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-700 cursor-not-allowed" readonly>
                                    <input type="hidden" name="year" value="<?php echo htmlspecialchars($computedSY, ENT_QUOTES); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Abstract -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Abstract <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute top-2 left-0 pl-3 text-gray-400"><i class="fas fa-align-left"></i></span>
                                <textarea name="abstract" rows="4" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-primary focus:border-blue-primary transition-all placeholder-gray-400" placeholder="Enter a brief summary of your research..." required></textarea>
                            </div>
                        </div>

                        <!-- Row 3: Keywords -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Keywords <span class="text-gray-400">(comma-separated)</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fas fa-tags"></i></span>
                                <input type="text" name="keywords" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-primary focus:border-blue-primary transition-all placeholder-gray-400" placeholder="e.g., machine learning, climate change, data mining">
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Add 3–8 keywords separated by commas to improve search visibility.</p>
                        </div>

                        <!-- Row 4: Members -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Group Member Name(s) <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fas fa-users"></i></span>
                                <textarea name="members" rows="2" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-primary focus:border-blue-primary transition-all placeholder-gray-400" placeholder="Enter member names separated by commas" required></textarea>
                            </div>
                        </div>

                        <!-- Row 5: Strand (readonly styled select) + Status (readonly Pending) -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Strand</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fas fa-user-graduate"></i></span>
                                    <select class="w-full pl-10 pr-8 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-700 cursor-not-allowed" disabled>
                                        <option selected><?php echo htmlspecialchars($strand ?: '—'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
                                <div class="w-full pl-3 pr-3 py-2 border border-green-300 rounded-lg bg-green-50 text-green-700 flex items-center">
                                    <span class="inline-flex items-center gap-2"><i class="fas fa-check-circle"></i> Pending</span>
                                </div>
                            </div>
                        </div>

                        <!-- Row 6: Files -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Poster <span class="text-gray-400">(Optional)</span></label>
                                <input type="file" name="image" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-lg file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-primary hover:file:bg-blue-100 transition-all duration-200">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Research Document (PDF) <span class="text-red-500">*</span></label>
                                <input type="file" name="document" accept=".pdf" class="w-full px-3 py-2 border border-gray-300 rounded-lg file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-primary hover:file:bg-blue-100 transition-all duration-200" required>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="w-full bg-blue-primary hover:bg-blue-secondary text-white py-3 rounded-lg transition-colors duration-200 flex items-center justify-center font-semibold shadow-sm">
                            <i class="fas fa-upload mr-2"></i>
                            Upload Research
                        </button>
                    </form>

                <script>
                // Countdown Timer
                function updateCountdown() {
                    const deadline = <?php echo $deadlineTimestamp; ?>;
                    const now = new Date().getTime();
                    const distance = deadline - now;
                    
                    if (distance < 0) {
                        document.getElementById('countdown').innerHTML = "Submission period has ended";
                        const form = document.getElementById('uploadForm');
                        if (form) form.style.display = 'none';
                        return;
                    }
                    
                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                    
                    let timeString = '';
                    if (days > 0) timeString += days + 'd, ';
                    if (hours > 0 || days > 0) timeString += hours + 'h, ';
                    timeString += minutes + 'm, ';
                    timeString += seconds + 's';
                    
                    document.getElementById('countdown').innerHTML = timeString;
                }
                
                // Update countdown every second
                setInterval(updateCountdown, 1000);
                updateCountdown(); // Initial call
                </script>
                
                <?php 
                }
            } elseif ($upcomingAnnouncement) { 
                ?>
                <div class="mb-6 p-4 bg-yellow-50 rounded-lg border border-yellow-100">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-3">
                        <h4 class="font-bold text-yellow-800 flex items-center">
                            <i class="fas fa-hourglass-start mr-2"></i>
                            Upcoming Submission Period
                        </h4>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 mt-2 sm:mt-0">
                            <i class="fas fa-clock mr-1.5"></i>
                            Not Yet Open
                        </span>
                    </div>
                    <p class="text-gray-700 font-medium"><?php echo htmlspecialchars($upcomingAnnouncement['title']); ?></p>
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-door-open mr-2 text-yellow-600"></i>
                            <span>Opens: <?php echo date('M j, Y g:i A', strtotime($upcomingAnnouncement['open_at'])); ?></span>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-calendar-alt mr-2 text-yellow-600"></i>
                            <span>Deadline: <?php echo date('M j, Y g:i A', strtotime($upcomingAnnouncement['deadline'])); ?></span>
                        </div>
                    </div>
                </div>
                <?php 
            } else { ?>
                <div class="text-center py-10">
                    <div class="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-times-circle text-3xl text-red-500"></i>
                    </div>
                    <div class="text-red-600 text-lg font-semibold mb-2">No Active Submission Period</div>
                    <p class="text-gray-600 max-w-md mx-auto">
                        There is currently no active submission period. Please wait for an announcement from the Teacher regarding the next submission deadline.
                    </p>
                </div>
            <?php } 
            ?>
        </section>

        <!-- Research Submissions Section (visible to all students) -->
        <section id="submissions" class="bg-white p-5 sm:p-6 rounded-xl shadow-lg card-hover transition-all duration-300">
            <div class="flex items-center mb-5">
                <i class="fas fa-list-alt text-2xl text-blue-primary mr-3"></i>
                <h3 class="text-xl font-bold text-gray-800">My Research Submissions</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b-2 border-gray-200">
                            <th class="text-left px-4 py-3 text-sm font-semibold text-gray-700">Title</th>
                            <th class="text-left px-4 py-3 text-sm font-semibold text-gray-700">Academic Year</th>
                            <th class="text-left px-4 py-3 text-sm font-semibold text-gray-700">Department</th>
                            <th class="text-left px-4 py-3 text-sm font-semibold text-gray-700">Status</th>
                            <th class="text-left px-4 py-3 text-sm font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($submissions) > 0): ?>
                            <?php foreach ($submissions as $submission): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors duration-150">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-800"><?php echo htmlspecialchars($submission['title']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-600"><?php echo htmlspecialchars($submission['year']); ?></td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?php echo htmlspecialchars($submission['strand'] ?? $strand); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="status-badge <?php echo $submission['status'] == 1 ? 'status-approved' : 'status-pending'; ?>">
                                            <?php echo $submission['status'] == 1 ? 'Approved' : 'Pending'; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex items-center space-x-3">
                                            <a href="<?php echo htmlspecialchars($submission['document']); ?>" 
                                               class="text-blue-600 hover:text-blue-800 transition-colors duration-200 flex items-center action-btn" 
                                               target="_blank">
                                                <i class="fas fa-file-pdf mr-1"></i>
                                                View
                                            </a>
                                            
                                            <?php if ($submission['status'] == 0): ?>
                                                <a href="edit_research.php?id=<?php echo $submission['id']; ?>" 
                                                   class="text-yellow-600 hover:text-yellow-800 transition-colors duration-200 flex items-center action-btn">
                                                    <i class="fas fa-edit mr-1"></i>
                                                    Edit
                                                </a>
                                                <a href="delete_research.php?id=<?php echo $submission['id']; ?>" 
                                                   class="text-red-600 hover:text-red-800 transition-colors duration-200 flex items-center action-btn"
                                                   onclick="return confirm('Are you sure you want to delete this research? This action cannot be undone.');">
                                                    <i class="fas fa-trash-alt mr-1"></i>
                                                    Delete
                                                </a>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">[Approved]</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-8 text-gray-500">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-folder-open text-4xl mb-3 text-gray-300"></i>
                                        <p>No research submitted yet.</p>
                                        <p class="text-sm mt-1">Upload your first research to get started.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- Scripts -->
    <script>
    // Profile modal functions
    function toggleModal() {
        const modal = document.getElementById('editProfileModal');
        if (!modal) return;
        modal.classList.toggle('hidden');
        const topControls = document.getElementById('topUserControls');
        const notifMenu = document.getElementById('notifMenu');
        // Prevent background scroll when modal is open and hide top controls (bell/profile)
        if (!modal.classList.contains('hidden')) {
            document.body.classList.add('overflow-hidden');
            if (topControls) topControls.classList.add('hidden');
            if (notifMenu && !notifMenu.classList.contains('hidden')) notifMenu.classList.add('hidden');
        } else {
            document.body.classList.remove('overflow-hidden');
            if (topControls) topControls.classList.remove('hidden');
        }
    }

    // Mobile menu functions
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const closeSidebar = document.getElementById('closeSidebar');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const repositoryDropdown = document.getElementById('repositoryDropdown');
        const dropdownMenu = document.getElementById('dropdownMenu');
        let chevron = null;
        if (repositoryDropdown) {
            chevron = repositoryDropdown.querySelector('.fa-chevron-down');
        }

        // Mobile menu toggle
        if (mobileMenuButton && closeSidebar && sidebar && overlay) {
            mobileMenuButton.addEventListener('click', function() {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            });

            closeSidebar.addEventListener('click', function() {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            });

            overlay.addEventListener('click', function() {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            });
        }

        // Repository dropdown
        if (repositoryDropdown && dropdownMenu && chevron) {
            repositoryDropdown.addEventListener('click', function(e) {
                if (window.innerWidth >= 1024) { // Only on desktop
                    e.preventDefault();
                    dropdownMenu.classList.toggle('hidden');
                    chevron.classList.toggle('rotate-180');
                    
                    if (!dropdownMenu.classList.contains('hidden')) {
                        dropdownMenu.classList.add('dropdown-menu');
                    }
                }
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!repositoryDropdown.contains(e.target)) {
                    dropdownMenu.classList.add('hidden');
                    chevron.classList.remove('rotate-180');
                }
            });
        }

        // Close mobile menu when clicking nav links
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 1024) {
                    setTimeout(() => {
                        sidebar.classList.add('-translate-x-full');
                        overlay.classList.add('hidden');
                        document.body.classList.remove('overflow-hidden');
                    }, 300);
                }
            });
        });

        // Profile dropdown functionality - aligned with Sub-Admin behavior
        const profileDropdown = document.getElementById('profileDropdown');
        const profileDropdownMenu = document.getElementById('profileDropdownMenu');

        if (profileDropdown && profileDropdownMenu) {
            profileDropdown.addEventListener('click', function(e) {
                e.preventDefault();
                if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
                e.stopPropagation();
                profileDropdownMenu.classList.toggle('hidden');
                profileDropdownMenu.style.display = profileDropdownMenu.classList.contains('hidden') ? 'none' : 'block';
                // Close notifications menu if open
                const notifMenuEl = document.getElementById('notifMenu');
                if (notifMenuEl && !notifMenuEl.classList.contains('hidden')) {
                    notifMenuEl.classList.add('hidden');
                }
            });

            // Prevent clicks inside the menu from bubbling to document
            profileDropdownMenu.addEventListener('click', function(e) {
                e.stopPropagation();
            });

            document.addEventListener('click', function(e) {
                if (!profileDropdown.contains(e.target) && !profileDropdownMenu.contains(e.target)) {
                    profileDropdownMenu.classList.add('hidden');
                    profileDropdownMenu.style.display = 'none';
                }
            });

            // Close on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    profileDropdownMenu.classList.add('hidden');
                    profileDropdownMenu.style.display = 'none';
                }
            });
        }

        // Notification dropdown functionality
        const notifBellBtn = document.getElementById('notifBellBtn');
        const notifMenu = document.getElementById('notifMenu');

        if (notifBellBtn && notifMenu) {
            notifBellBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                notifMenu.classList.toggle('hidden');
                // Close profile dropdown if open
                const profileMenuEl = document.getElementById('profileDropdownMenu');
                if (profileMenuEl && !profileMenuEl.classList.contains('hidden')) {
                    profileMenuEl.classList.add('hidden');
                    profileMenuEl.style.display = 'none';
                }
                // Mark announcements as read once and update badge immediately
                if (!window._markedAnnouncements) {
                    window._markedAnnouncements = true;
                    // Optimistically remove badge and header count
                    const badge = notifBellBtn.querySelector('span');
                    if (badge) badge.remove();
                    const headerCount = notifMenu.querySelector('.px-4.py-3 .text-xs.text-gray-500');
                    if (headerCount) headerCount.classList.add('hidden');

                    fetch('mark_announcements_read.php', { method: 'POST' })
                        .then(() => {})
                        .catch(() => { /* ignore errors; UI already updated optimistically */ });
                }
            });

            // Keep open when clicking inside
            notifMenu.addEventListener('click', function(e) {
                e.stopPropagation();
            });

            // Close when clicking outside
            document.addEventListener('click', function(e) {
                if (!notifMenu.contains(e.target) && !notifBellBtn.contains(e.target)) {
                    notifMenu.classList.add('hidden');
                }
            });

            // Close on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    notifMenu.classList.add('hidden');
                }
            });
        }

        // Direct bind SweetAlert2 to dropdown Sign Out (fallback to ensure it always shows)
        const studentDropdownLogout = document.querySelector('#profileDropdownMenu a[href="logout.php"]');
        if (studentDropdownLogout) {
            studentDropdownLogout.addEventListener('click', function(e) {
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

    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('editProfileModal');
        if (modal && !modal.contains(e.target) && e.target.classList.contains('bg-opacity-75')) {
            toggleModal();
        }
    });
    </script>
    <script>
        function togglePassword(inputId) {
            try {
                var input = document.getElementById(inputId);
                if (!input) return;
                var icon = document.getElementById(inputId + '_icon');
                var isPassword = input.getAttribute('type') === 'password';
                input.setAttribute('type', isPassword ? 'text' : 'password');
                if (icon) {
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                }
            } catch (_) {}
        }
    </script>
</body>
</html>