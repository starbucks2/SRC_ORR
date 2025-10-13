<?php
session_start();
include 'db.php';

// Check if a user is logged in (either admin or sub-admin)
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['subadmin_id'])) {
    $_SESSION['error'] = "You must be logged in to access this page.";
    header("Location: login.php");
    exit();
}

// By default, assume the user does not have permission
$can_manage_announcements = false;

// An admin always has permission
if (isset($_SESSION['admin_id'])) {
    $can_manage_announcements = true;
} 
// A sub-admin needs the specific 'manage_announcements' permission
elseif (isset($_SESSION['subadmin_id'])) {
    $permissions = json_decode($_SESSION['permissions'] ?? '[]', true);
    if (in_array('manage_announcements', $permissions)) {
        $can_manage_announcements = true;
    }
}

// If the user does not have permission, redirect them to their dashboard
if (!$can_manage_announcements) {
    $_SESSION['error'] = "You do not have permission to manage announcements.";
    $redirect_url = isset($_SESSION['subadmin_id']) ? 'subadmin_dashboard.php' : 'admin_dashboard.php';
    header("Location: $redirect_url");
    exit();
}

// Fetch announcements from the database
try {
    $stmt = $conn->prepare("SELECT * FROM announcements ORDER BY created_at DESC");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    $announcements = [];
}
?>

<!DOCTYPE html>
<html lang="en" class="<?= isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'] ? 'dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Announcements</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
      // Disable dark mode on mobile view; re-enable for tablet/desktop if user prefers dark
      document.addEventListener('DOMContentLoaded', function() {
        const htmlEl = document.documentElement;
        const enableDarkPref = <?= isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'] ? 'true' : 'false' ?>;
        function applyDarkByViewport() {
          const isMobile = window.innerWidth < 768; // < md breakpoint
          if (isMobile) {
            htmlEl.classList.remove('dark');
          } else {
            if (enableDarkPref) {
              htmlEl.classList.add('dark');
            } else {
              htmlEl.classList.remove('dark');
            }
          }
        }
        applyDarkByViewport();
        window.addEventListener('resize', applyDarkByViewport);
      });
    </script>
    <style>
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .status-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .status-active { background-color: #dcfce7; color: #166534; }
        .status-expired { background-color: #fee2e2; color: #991b1b; }
        .status-upcoming { background-color: #e0e7ff; color: #3730a3; }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 flex">

<!-- Dynamically include the correct sidebar -->
<?php include 'admin_sidebar.php'; ?>

<!-- Main Content -->
<main class="flex-1 p-6 md:p-10">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 md:p-8 mb-8">
        <h2 class="text-3xl font-bold text-gray-800 dark:text-white mb-6">📢 Manage Announcements</h2>

        <!-- Display success or error messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    Swal.fire({
                        icon: 'success',
                        title: <?= json_encode($_SESSION['success']) ?>,
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
                        title: <?= json_encode($_SESSION['error']) ?>,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2500,
                        timerProgressBar: true
                    });
                });
            </script>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Add New Announcement Form -->
        <form action="add_announcement.php" method="POST" class="mb-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div>
                    <label for="title" class="block text-gray-700 dark:text-gray-300 font-medium mb-2">Announcement Title</label>
                    <input type="text" id="title" name="title" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white dark:border-gray-600" placeholder="Enter announcement title">
                </div>
                <div>
                    <label for="open_at" class="block text-gray-700 dark:text-gray-300 font-medium mb-2">Opens At</label>
                    <input type="datetime-local" id="open_at" name="open_at" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white dark:border-gray-600" placeholder="mm/dd/yyyy --:-- --">
                </div>
                <div>
                    <label for="deadline" class="block text-gray-700 dark:text-gray-300 font-medium mb-2">Submission Deadline</label>
                    <input type="datetime-local" id="deadline" name="deadline" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white dark:border-gray-600" placeholder="mm/dd/yyyy --:-- --">
                </div>
                <div>
                    <label for="strand" class="block text-gray-700 dark:text-gray-300 font-medium mb-2">Target Department</label>
                    <select name="strand" id="strand" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white dark:border-gray-600">
                        <option value="">All Departments</option>
                        <option value="CCS">CCS (College of Computer Studies) Only</option>
                        <option value="COE">COE (College of Education) Only</option>
                        <option value="CBS">CBS (College of Business Studies) Only</option>
                        <option value="Senior High School">Senior High School Only</option>
                    </select>
                </div>
            </div>
            <div class="mt-4">
                <label for="content" class="block text-gray-700 dark:text-gray-300 font-medium mb-2">Announcement Content</label>
                <textarea id="content" name="content" rows="4" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white dark:border-gray-600" placeholder="Enter announcement details"></textarea>
            </div>
            <div class="mt-4">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-plus-circle mr-2"></i>Post Announcement
                </button>
            </div>
        </form>

          <!-- Display Current Announcements -->
        <div>
            <h3 class="text-xl font-semibold text-gray-800 dark:text-white mb-4">Recent Announcements</h3>
            <?php if ($announcements): ?>
                <?php foreach ($announcements as $announcement): ?>
                    <?php
                    $now = new DateTime();
                    $deadline = !empty($announcement['deadline']) ? new DateTime($announcement['deadline']) : null;
                    $opensAt = !empty($announcement['open_at']) ? new DateTime($announcement['open_at']) : null;

                    $isUpcoming = $opensAt && $now < $opensAt;
                    $isExpired = $deadline && $deadline < $now;

                    if ($isUpcoming) {
                        $statusClass = 'status-upcoming';
                        $statusText = 'Upcoming';
                    } elseif ($isExpired) {
                        $statusClass = 'status-expired';
                        $statusText = 'Expired';
                    } else {
                        $statusClass = 'status-active';
                        $statusText = 'Active';
                    }
                    ?>
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg mb-4 relative border-l-4 border-blue-500">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <h4 class="text-lg font-semibold text-blue-900 dark:text-blue-300"><?= htmlspecialchars($announcement['title']) ?></h4>
                                <span class="inline-block mt-1 px-2 py-1 rounded-full text-xs font-medium <?= empty($announcement['strand']) ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' ?>">
                                    <?= empty($announcement['strand']) ? 'All Departments' : htmlspecialchars($announcement['strand']) . ' Department Only' ?>
                                </span>
                            </div>
                            <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
                        </div>
                        <p class="text-gray-600 dark:text-gray-300 mt-2"><?= nl2br(htmlspecialchars($announcement['content'])) ?></p>
                        <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            <?php if (!empty($announcement['open_at'])): ?>
                                <div>Opens At: <?= date("F j, Y g:i A", strtotime($announcement['open_at'])) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($announcement['deadline'])): ?>
                                <div>Submission Deadline: <?= date("F j, Y g:i A", strtotime($announcement['deadline'])) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="absolute top-4 right-4 flex space-x-2">
                            <a href="edit_announcement.php?id=<?= $announcement['id'] ?>"
                               class="inline-flex items-center gap-1 text-sm font-semibold px-3 py-1.5 rounded-lg shadow-sm border border-yellow-400 bg-yellow-100 text-yellow-800 hover:bg-yellow-200 hover:text-yellow-900 dark:bg-yellow-900 dark:text-yellow-200 dark:border-yellow-500 dark:hover:bg-yellow-800 dark:hover:text-yellow-100 transition duration-150">
                                <i class="fas fa-pen"></i> Edit
                            </a>
                            <form action="delete_announcement.php" method="POST" class="inline sa-delete-form">
                                <input type="hidden" name="id" value="<?= $announcement['id'] ?>">
                                <button type="submit"
                                    class="inline-flex items-center gap-1 text-sm font-semibold px-3 py-1.5 rounded-lg shadow-sm border border-red-400 bg-red-100 text-red-700 hover:bg-red-200 hover:text-red-900 dark:bg-red-900 dark:text-red-200 dark:border-red-500 dark:hover:bg-red-800 dark:hover:text-red-100 transition duration-150">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-gray-600 dark:text-gray-400">No announcements posted yet.</p>
            <?php endif; ?>
        </div>
    </div>
</main>

</body>
<script>
// SweetAlert2 delete confirmation
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.sa-delete-form').forEach(form => {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      Swal.fire({
        title: 'Delete this announcement?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it',
      }).then((result) => {
        if (result.isConfirmed) {
          this.submit();
        }
      });
    });
  });
});
</script>
</html>