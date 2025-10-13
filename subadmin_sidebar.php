<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine whether current user may see the "View All Students" link.
// We allow if the user is an admin, or if their permissions include
// 'view_students' OR 'verify_students' OR the department-specific variants.
$is_admin = isset($_SESSION['admin_id']);
$is_subadmin = isset($_SESSION['subadmin_id']);
$permissions = [];
$department = '';
if (isset($_SESSION['permissions'])) {
    $permissions = is_array($_SESSION['permissions']) ? $_SESSION['permissions'] : (json_decode($_SESSION['permissions'], true) ?: []);
}
// Prefer department from session, fallback to legacy strand
if (isset($_SESSION['department']) && $_SESSION['department'] !== '') {
    $department = strtolower($_SESSION['department']);
} elseif (isset($_SESSION['strand'])) {
    $department = strtolower($_SESSION['strand']);
}
$view_permission = 'view_students_' . $department;
$verify_permission = 'verify_students_' . $department;
$view_allowed = false;
if ($is_admin) {
    $view_allowed = true;
} else {
    if (in_array('view_students', $permissions) || in_array($view_permission, $permissions) || in_array('verify_students', $permissions) || in_array($verify_permission, $permissions)) {
        $view_allowed = true;
    }
}
?>

<!-- Responsive Sidebar for Sub-Admin -->
<style>
/* Overlay for mobile sidebar */
#sidebar-overlay {
    display: none;
}
@media (max-width: 1023px) {
    #sidebar-overlay.active {
        display: block;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.4);
        z-index: 39;
    }
}
</style>

<div id="sidebar-overlay"></div>
<?php
    // Determine current script name for active link highlighting
    $___current = basename(parse_url($_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF'], PHP_URL_PATH));
    // Capture grade filter if present
    $___grade = isset($_GET['grade']) ? (int)$_GET['grade'] : null;
    if (!function_exists('___is_active')) {
        function ___is_active($current, $targets) {
            if (!is_array($targets)) { $targets = [$targets]; }
            return in_array($current, $targets, true);
        }
    }
    // Active class avoids font-weight changes to keep width consistent across pages
    $active_cls = ' bg-blue-700 ring-1 ring-blue-600 border-l-4 border-yellow-300 shadow-md';
    $base_link_cls = 'flex items-center p-3 rounded-lg transition w-full';
    $hover_cls = ' hover:bg-blue-700';
?>
<aside id="subadmin-sidebar" class="flex-none w-72 min-w-[18rem] bg-gradient-to-b from-blue-900 to-blue-800 text-white min-h-screen p-6 shadow-xl fixed inset-y-0 left-0 z-40 transform -translate-x-full lg:relative lg:translate-x-0 transition-transform duration-300 ease-in-out overflow-y-auto max-h-screen">
    <div class="flex items-center justify-between mb-8 relative">
        <div class="flex items-center">
            <img src="srclogo.png" alt="School Logo" class="h-20 w-auto rounded-full border-2 border-yellow-300">
            <h2 class="ml-4 text-2xl font-bold">Research Adviser</h2>
        </div>
        <button id="close-sidebar-btn" class="lg:hidden text-white hover:text-yellow-300 absolute top-0 right-0 mt-2 mr-2">
            <i class="fas fa-times text-2xl"></i>
        </button>
    </div>
    <ul class="space-y-3">
        <li><a href="subadmin_dashboard.php" class="<?= $base_link_cls . (___is_active($___current, 'subadmin_dashboard.php') ? $active_cls : $hover_cls) ?>"><i class="fas fa-home mr-3"></i> Dashboard</a></li>
        <li><a href="subadmin_research_approvals.php" class="<?= $base_link_cls . (___is_active($___current, 'subadmin_research_approvals.php') ? $active_cls : $hover_cls) ?>"><i class="fas fa-book mr-3"></i> Research Approvals</a></li>
        <li><a href="subadmin_archived_research.php" class="<?= $base_link_cls . (___is_active($___current, 'subadmin_archived_research.php') ? $active_cls : $hover_cls) ?>"><i class="fas fa-archive mr-3"></i> Archived Research</a></li>
        <li>
            <a href="subadmin_verify_students.php" class="<?= $base_link_cls . (___is_active($___current, 'subadmin_verify_students.php') ? $active_cls : $hover_cls) ?> relative">
                <i class="fas fa-user-check mr-3"></i> Verify Students
                <?php
                // Show badge with count of unverified students from subadmin's department
                try {
                    include_once 'db.php';
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM students WHERE is_verified = 0");
                    if (!$is_admin && !empty($department)) {
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM students WHERE is_verified = 0 AND (LOWER(COALESCE(department,'')) = LOWER(?) OR LOWER(COALESCE(strand,'')) = LOWER(?))");
                        $stmt->execute([$department, $department]);
                    } else {
                        $stmt->execute();
                    }
                    $pending_count = $stmt->fetchColumn();
                    if ($pending_count > 0) {
                        echo '<span class="absolute right-2 top-2 bg-red-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">' . $pending_count . '</span>';
                    }
                } catch (Exception $e) {
                    // Fail silently
                }
                ?>
            </a>
        </li>
        <li><a href="repository.php?department=<?= htmlspecialchars($_SESSION['department'] ?? '') ?>" class="<?= $base_link_cls . (___is_active($___current, 'repository.php') ? $active_cls : $hover_cls) ?>"><i class="fas fa-database mr-3"></i> Research Repository</a></li>
    <?php if ($is_admin || $is_subadmin): ?>
        <li><a href="subadmin_view_students.php" class="<?= $base_link_cls . (___is_active($___current, 'subadmin_view_students.php') ? $active_cls : $hover_cls) ?>"><i class="fas fa-user-tag mr-3"></i> View Students</a></li>
    <?php endif; ?>
        <li>
            <a href="subadmin_announcements.php" class="<?= $base_link_cls . (___is_active($___current, 'subadmin_announcements.php') ? $active_cls : $hover_cls) ?> relative">
                <i class="fas fa-bullhorn mr-3"></i> Announcements
                <?php
                // Show badge if there are unread/new announcements for subadmin
                try {
                    include_once 'db.php';
                    if (isset($_SESSION['subadmin_id'])) {
                        $subadmin_id = $_SESSION['subadmin_id'];
                        // Count announcements not yet acknowledged by this subadmin
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM announcements a WHERE NOT EXISTS (SELECT 1 FROM announcement_reads ar WHERE ar.announcement_id = a.id AND ar.subadmin_id = ?) AND a.target_audience IN ('all','subadmin')");
                        $stmt->execute([$subadmin_id]);
                        $unread_count = $stmt->fetchColumn();
                        if ($unread_count > 0) {
                            echo '<span class="absolute right-2 top-2 bg-red-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">' . $unread_count . '</span>';
                        }
                    }
                } catch (Exception $e) {
                    // Fail silently
                }
                ?>
            </a>
        </li>
        
        <li><a href="logout.php" class="flex items-center p-3 rounded-lg hover:bg-red-700 text-red-200"><i class="fas fa-sign-out-alt mr-3"></i> Sign Out</a></li>
    </ul>
</aside>

<!-- Mobile sidebar toggle button -->
<button id="open-sidebar-btn" class="fixed top-4 left-4 z-50 bg-blue-900 text-white p-2 rounded-full shadow-lg lg:hidden">
    <i class="fas fa-bars text-2xl"></i>
</button>

<!-- SweetAlert2 for logout confirmation -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Attach a single delegated handler for logout confirmation across pages using this sidebar
if (!window._logoutConfirmBound) {
  window._logoutConfirmBound = true;
  document.addEventListener('click', function (e) {
    const a = e.target.closest('a[href]');
    if (!a) return;
    const href = a.getAttribute('href') || '';
    const isLogout = href.endsWith('logout.php') || href.endsWith('admin_logout.php');
    if (!isLogout) return;
    e.preventDefault();
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
</script>

<script>
// Sidebar open/close logic for mobile
const sidebar = document.getElementById('subadmin-sidebar');
const openBtn = document.getElementById('open-sidebar-btn');
const closeBtn = document.getElementById('close-sidebar-btn');
const overlay = document.getElementById('sidebar-overlay');

function hideHeaderExtrasForMobile() {
    if (window.innerWidth < 1024) {
        const hdr = document.getElementById('subProfileDropdown');
        if (hdr) {
            hdr.classList.add('hidden');
        }
        // Also close any open dropdown menus if present
        const notifMenu = document.getElementById('subNotifMenu');
        if (notifMenu) notifMenu.classList.add('hidden');
        const profileMenu = document.getElementById('subProfileDropdownMenu');
        if (profileMenu) profileMenu.classList.add('hidden');
    }
}

function showHeaderExtrasForMobile() {
    if (window.innerWidth < 1024) {
        const hdr = document.getElementById('subProfileDropdown');
        if (hdr) {
            hdr.classList.remove('hidden');
        }
    }
}

function openSidebar() {
    sidebar.classList.remove('-translate-x-full');
    overlay.classList.add('active');
    hideHeaderExtrasForMobile();
}
function closeSidebar() {
    sidebar.classList.add('-translate-x-full');
    overlay.classList.remove('active');
    showHeaderExtrasForMobile();
}

openBtn.addEventListener('click', openSidebar);
closeBtn.addEventListener('click', closeSidebar);
overlay.addEventListener('click', closeSidebar);

// Ensure sidebar is visible on large screens
window.addEventListener('resize', function() {
    if (window.innerWidth >= 1024) {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('active');
    } else {
        sidebar.classList.add('-translate-x-full');
    }
    // Reset visibility appropriately on resize transitions
    if (window.innerWidth >= 1024) {
        const hdr = document.getElementById('subProfileDropdown');
        if (hdr) hdr.classList.remove('hidden');
    } else if (!overlay.classList.contains('active')) {
        const hdr = document.getElementById('subProfileDropdown');
        if (hdr) hdr.classList.remove('hidden');
    }
});
</script>