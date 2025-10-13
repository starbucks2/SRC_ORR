<?php
session_start();
include 'db.php';

// Only admins can access
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['error'] = "You must be logged in as an admin.";
    header("Location: login.php");
    exit();
}

// Ensure archive column exists
try {
    $chk = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sub_admins' AND COLUMN_NAME = 'is_archived'");
    $chk->execute();
    if ((int)$chk->fetchColumn() === 0) {
        $conn->exec("ALTER TABLE sub_admins ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER permissions");
    }
} catch (Throwable $e) { /* ignore */ }

// Handle restore
if (isset($_GET['restore'])) {
    $id = (int)($_GET['restore'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $conn->prepare("UPDATE sub_admins SET is_archived = 0 WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                $_SESSION['success'] = "Sub-admin restored successfully.";
            } else {
                $_SESSION['error'] = "Sub-admin not found or already active.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error restoring sub-admin: " . $e->getMessage();
        }
    }
    header("Location: archived_subadmins.php");
    exit();
}

// Fetch archived sub-admins
$stmt = $conn->prepare("SELECT * FROM sub_admins WHERE COALESCE(is_archived,0) = 1 ORDER BY id DESC");
$stmt->execute();
$subadmins = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Sub-Admins</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100 flex">
    <?php include 'admin_sidebar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 lg:p-10 w-full max-w-7xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 lg:p-8">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
                <h2 class="text-2xl sm:text-3xl font-bold text-blue-900">Archived Sub-Admins</h2>
                <a href="manage_subadmins.php" class="inline-flex items-center justify-center bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 w-full sm:w-auto">
                    <i class="fas fa-users-cog mr-2"></i> Back to Manage
                </a>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        Swal.fire({ icon: 'success', title: <?= json_encode($_SESSION['success']) ?>, timer: 1800, showConfirmButton: false, toast: true, position: 'top-end' });
                    });
                </script>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        Swal.fire({ icon: 'error', title: 'Action failed', text: <?= json_encode($_SESSION['error']) ?> });
                    });
                </script>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profile</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Archived</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($subadmins)): ?>
                            <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">No archived sub-admins</td></tr>
                        <?php else: foreach ($subadmins as $sa): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="w-10 h-10 rounded-full overflow-hidden bg-gray-100 border flex items-center justify-center">
                                        <?php if (!empty($sa['profile_pic'])): ?>
                                            <img src="images/<?= htmlspecialchars($sa['profile_pic']) ?>" alt="Profile" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <i class="fas fa-user text-gray-400"></i>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($sa['fullname']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($sa['email']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($sa['strand'] ?? '') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">Yes</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="archived_subadmins.php?restore=<?= (int)$sa['id'] ?>" class="text-green-600 hover:text-green-800 restore-link"><i class="fas fa-undo"></i> Restore</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('.restore-link').forEach(function(el){
            el.addEventListener('click', function(e){
                const href = this.getAttribute('href');
                if (typeof Swal === 'undefined') return;
                e.preventDefault();
                Swal.fire({
                    title: 'Restore this sub-admin?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#16a34a',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Yes, restore'
                }).then((res)=>{ if(res.isConfirmed){ window.location.href = href; }});
            });
        });
    });
    </script>
</body>
</html>
