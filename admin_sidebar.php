<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine if the current user has permission to view students.
$is_admin = isset($_SESSION['admin_id']);
$permissions = [];
$strand = '';
if (isset($_SESSION['permissions'])) {
    $permissions = is_array($_SESSION['permissions']) ? $_SESSION['permissions'] : (json_decode($_SESSION['permissions'], true) ?: []);
}
if (isset($_SESSION['strand'])) {
    $strand = strtolower($_SESSION['strand']);
}
$view_allowed = $is_admin || in_array('view_students', $permissions) || in_array('verify_students', $permissions) || in_array('view_students_' . $strand, $permissions) || in_array('verify_students_' . $strand, $permissions);
?>

<style>
#admin-sidebar-overlay {
    display: none;
}
@media (max-width: 1023px) {
    #admin-sidebar-overlay.active {
        display: block;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.4);
        z-index: 39;
    }
}
</style>
<div id="admin-sidebar-overlay"></div>
<aside id="admin-sidebar" class="flex-none w-72 min-w-[18rem] bg-gradient-to-b from-blue-900 to-blue-800 text-white min-h-screen p-6 shadow-xl fixed inset-y-0 left-0 z-40 transform -translate-x-full lg:relative lg:translate-x-0 transition-transform duration-300 ease-in-out overflow-y-auto max-h-screen">
    <div class="text-center mb-8">
        <img src="srclogo.png" alt="School Logo" class="h-20 w-auto mx-auto rounded-full border-2 border-yellow-300">
        <h2 class="mt-4 text-2xl font-bold">Admin Panel</h2>
        <button id="close-admin-sidebar-btn" class="lg:hidden text-white hover:text-yellow-300 absolute top-6 right-6">
            <i class="fas fa-times text-2xl"></i>
        </button>
    </div>
    <?php
        // Determine current script name for active link highlighting
        $___current = basename(parse_url($_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF'], PHP_URL_PATH));
        // Also capture current grade filter if present to differentiate links
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
    <ul class="space-y-3">
        <li><a href="admin_dashboard.php" class="<?= $base_link_cls . (___is_active($___current, 'admin_dashboard.php') ? $active_cls : $hover_cls) ?>"><i class="fas fa-home mr-3"></i> Dashboard</a></li>
        <li><a href="research_approvals.php" class="<?= $base_link_cls . (___is_active($___current, 'research_approvals.php') ? $active_cls : $hover_cls) ?>"><i class="fas fa-book mr-3"></i> Research Approvals</a></li>
        <li><a href="archived_research.php" class="<?= $base_link_cls . (___is_active($___current, 'archived_research.php') ? $active_cls : $hover_cls) ?>"><i class="fas fa-archive mr-3"></i> Archived Research</a></li>
        <?php 
        $can_verify = $is_admin || in_array('verify_students', $permissions) || in_array('verify_students_' . $strand, $permissions);
        if ($can_verify): 
        ?>
        <li><a href="verify_students.php" class="<?= $base_link_cls . (___is_active($___current, 'verify_students.php') ? $active_cls : $hover_cls) ?> relative">
            <i class="fas fa-user-check mr-3"></i> Verify Students
            <?php
            // Show badge with count of unverified students
            try {
                include_once 'db.php';
                $stmt = $conn->prepare("SELECT COUNT(*) FROM students WHERE is_verified = 0");
                if (!$is_admin && isset($_SESSION['strand'])) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM students WHERE is_verified = 0 AND LOWER(strand) = LOWER(?)");
                    $stmt->execute([$_SESSION['strand']]);
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
        </a></li>
        <?php endif; ?>
        <li><a href="manage_subadmins.php" class="<?= $base_link_cls . (___is_active($___current, 'manage_subadmins.php') ? $active_cls : $hover_cls) ?>"><i class="fas fa-users-cog mr-3"></i> Manage Sub-Admins</a></li>
        <?php if ($is_admin): ?>
        <li><a href="archived_subadmins.php" class="<?= $base_link_cls . (___is_active($___current, 'archived_subadmins.php') ? $active_cls : $hover_cls) ?>"><i class="fas fa-archive mr-3"></i> Archived Sub-Admins</a></li>
        <?php endif; ?>
        <li><a href="repository.php?department=CCS" class="<?= $base_link_cls . (___is_active($___current, 'repository.php') ? $active_cls : $hover_cls) ?>"><i class="fas fa-database mr-3"></i> Research Repository</a></li>
    <?php if ($view_allowed): ?>
    <li><a href="view_students.php" class="<?= $base_link_cls . (___is_active($___current, 'view_students.php') ? $active_cls : $hover_cls) ?>"><i class="fas fa-user-tag mr-3"></i> View Students</a></li>
    <?php endif; ?>
        <li><a href="announcements.php" class="<?= $base_link_cls . (___is_active($___current, 'announcements.php') ? $active_cls : $hover_cls) ?>"><i class="fas fa-bullhorn mr-3"></i> Announcements</a></li>
        <!-- New: Upload Research Project -->
        <li><a href="admin_upload_research.php" class="<?= $base_link_cls . (___is_active($___current, 'admin_upload_research.php') ? $active_cls : $hover_cls) ?>"><i class="fas fa-upload mr-3"></i> Upload Research Project</a></li>
        <!-- New: Create Students via Import -->
        <?php 
            $strandPerm = isset($_SESSION['strand']) ? strtolower($_SESSION['strand']) : '';
            $can_import_link = $is_admin 
                || in_array('import_students', $permissions) 
                || ($strandPerm && in_array('import_students_' . $strandPerm, $permissions));
            if ($can_import_link):
        ?>
        <li><a href="admin_import_students.php" class="<?= $base_link_cls . (___is_active($___current, 'admin_import_students.php') ? $active_cls : $hover_cls) ?>"><i class="fas fa-file-import mr-3"></i> Create Students (Import Excel/CSV)</a></li>
        <?php endif; ?>
        <li><a href="admin_logout.php" class="flex items-center p-3 rounded-lg hover:bg-red-700 text-red-200"><i class="fas fa-sign-out-alt mr-3"></i> Sign Out</a></li>
    </ul>
</aside>
<!-- Mobile sidebar toggle button -->
<button id="open-admin-sidebar-btn" class="fixed top-4 left-4 z-50 bg-blue-900 text-white p-2 rounded-full shadow-lg lg:hidden">
    <i class="fas fa-bars text-2xl"></i>
</button>
<!-- SweetAlert2 for logout confirmation -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Attach a single delegated handler for logout confirmation across the admin pages
if (!window._logoutConfirmBound) {
  window._logoutConfirmBound = true;
  document.addEventListener('click', function (e) {
    const a = e.target.closest('a[href]');
    if (!a) return;
    const href = a.getAttribute('href') || '';
    // Match admin and shared logout endpoints
    const isLogout = href.endsWith('logout.php') || href.endsWith('admin_logout.php');
    if (!isLogout) return;
    e.preventDefault();
    if (typeof Swal === 'undefined') {
      // Fallback confirm if SweetAlert2 failed to load
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
const adminSidebar = document.getElementById('admin-sidebar');
const openAdminBtn = document.getElementById('open-admin-sidebar-btn');
const closeAdminBtn = document.getElementById('close-admin-sidebar-btn');
const adminOverlay = document.getElementById('admin-sidebar-overlay');

function openAdminSidebar() {
    adminSidebar.classList.remove('-translate-x-full');
    adminOverlay.classList.add('active');
}
function closeAdminSidebar() {
    adminSidebar.classList.add('-translate-x-full');
    adminOverlay.classList.remove('active');
}

openAdminBtn.addEventListener('click', openAdminSidebar);
closeAdminBtn.addEventListener('click', closeAdminSidebar);
adminOverlay.addEventListener('click', closeAdminSidebar);

// Ensure sidebar is visible on large screens
window.addEventListener('resize', function() {
    if (window.innerWidth >= 1024) {
        adminSidebar.classList.remove('-translate-x-full');
        adminOverlay.classList.remove('active');
    } else {
        adminSidebar.classList.add('-translate-x-full');
    }
});
</script>