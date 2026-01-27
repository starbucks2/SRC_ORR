<?php
session_start();
include 'db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['error'] = "You must be logged in as an admin to view activity logs.";
    header("Location: login.php");
    exit();
}

// Ensure the activity_logs table exists (defensive)
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        actor_type VARCHAR(20) NOT NULL,
        actor_id VARCHAR(64) NULL,
        action VARCHAR(100) NOT NULL,
        details JSON NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    // Fail softly; the page will show an error below if queries fail
}

// Pagination
$per_page = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// Search filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'all';
$where = '';
$params = [];

// Build WHERE conditions
$clauses = [];
if ($search) {
    // MySQL-compatible search: cast JSON to text for LIKE
    $clauses[] = "(action LIKE :search OR actor_type LIKE :search OR CAST(details AS CHAR) LIKE :search)";
    $params[':search'] = "%$search%";
}
if (in_array($type, ['admin','subadmin','student','system'], true)) {
    $clauses[] = "actor_type = :actor_type";
    $params[':actor_type'] = $type;
}
$where = $clauses ? ('WHERE ' . implode(' AND ', $clauses)) : '';

// Get total count for pagination
try {
    $count_sql = "SELECT COUNT(*) FROM activity_logs $where";
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $total_logs = $stmt->fetchColumn();
    $total_pages = ceil($total_logs / $per_page);

    // Get logs for current page
    $sql = "SELECT * FROM activity_logs $where ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);
    
    // Bind search parameters if any
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error fetching activity logs: " . $e->getMessage();
}

// Defensive fallback: if no filters applied and no logs returned, fetch latest items like the dashboard does
if (empty($error) && empty($logs) && !$search && ($type === 'all')) {
    try {
        $fallback = $conn->prepare("SELECT id, actor_type, actor_id, action, details, created_at FROM activity_logs ORDER BY created_at DESC, id DESC LIMIT 50");
        $fallback->execute();
        $fbLogs = $fallback->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($fbLogs)) {
            $logs = $fbLogs;
            $total_logs = count($fbLogs);
            $total_pages = 1;
            $page = 1; $offset = 0; $per_page = 50;
        }
    } catch (Throwable $e) { /* ignore */ }
}

// Mark logs as viewed so the dashboard badge clears
$_SESSION['logs_last_view'] = date('Y-m-d H:i:s');
$_SESSION['logs_viewed_ack'] = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Prevent the browser from restoring previous scroll position when navigating
        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }
    </script>
    <style>
        /* Ensure all cells in the logs table align to the top for consistent visual placement */
        table.activity-table th, table.activity-table td { vertical-align: top !important; }
        /* Make the top anchor take no space */
        #top { display: block; height: 0; margin: 0; padding: 0; position: absolute; top: 0; left: 0; }
        /* Force the main content to start at the very top */
        body { margin: 0; padding: 0; }
        main { margin-top: 0 !important; padding-top: 0 !important; }
        /* Override any default spacing from Tailwind or other CSS */
        .ml-0.md\:ml-64 { margin-top: 0 !important; }
        #activityRoot { margin-top: 0 !important; }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'admin_sidebar.php'; ?>

    <main class="ml-0 md:ml-64 px-4 md:px-8 py-0">
        <!-- Anchor target for sidebar link (zero-height, absolute) -->
        <a id="top"></a>
        <div id="activityRoot" class="bg-white rounded-lg shadow-md p-6 mt-0">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <h1 class="text-2xl font-bold text-gray-800">Activity Logs</h1>
                <div class="w-full md:w-auto flex gap-2 items-center">
                    <form method="get" class="flex-1 md:flex-none flex gap-2 items-center">
                        <div class="relative">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Search logs..." 
                                   class="w-full md:w-64 pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <button type="submit" class="absolute left-3 top-2.5 text-gray-400">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if ($search): ?>
                                <a href="activity_logs.php" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        <select name="type" class="border rounded-lg px-3 py-2 text-sm">
                            <?php
                                $typeOptions = [
                                    'all' => 'All Actors',
                                    'admin' => 'Admins',
                                    'subadmin' => 'Sub-admins',
                                    'student' => 'Students',
                                    'system' => 'System'
                                ];
                                foreach ($typeOptions as $val => $label) {
                                    $sel = ($type === $val) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($val, ENT_QUOTES) . '" ' . $sel . '>' . htmlspecialchars($label) . '</option>';
                                }
                            ?>
                        </select>
                        <button type="submit" class="px-3 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Filter</button>
                    </form>
                    <button id="clearLogsBtn" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center gap-2">
                        <i class="fas fa-trash"></i>
                        <span>Clear Activity Logs</span>
                    </button>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white activity-table">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                    <?= $search ? 'No matching logs found' : 'No activity logs.' ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $iconMap = [
                                'upload_research' => ['fa-upload', 'text-blue-600'],
                                'approve_student' => ['fa-user-check', 'text-green-600'],
                                'reject_student' => ['fa-user-times', 'text-red-600'],
                                'post_announcement' => ['fa-bullhorn', 'text-amber-600'],
                                'archive_research' => ['fa-archive', 'text-purple-600']
                            ];
                            
                            foreach ($logs as $log): 
                                $action = $log['action'];
                                $icon = $iconMap[$action][0] ?? 'fa-info-circle';
                                $color = $iconMap[$action][1] ?? 'text-gray-500';
                                $actionLabel = ucfirst(str_replace('_', ' ', $action));
                                $details = !empty($log['details']) ? json_decode($log['details'], true) : [];
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap align-top">
                                        <div class="flex items-start leading-tight">
                                            <i class="fas <?= $icon ?> <?= $color ?> mr-2"></i>
                                            <span class="font-medium"><?= htmlspecialchars($actionLabel) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap align-top">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                            <?= strtoupper(htmlspecialchars($log['actor_type'])) ?>
                                        </span>
                                        <?php if ($log['actor_id']): ?>
                                            <span class="text-xs text-gray-500">(ID: <?= htmlspecialchars($log['actor_id']) ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <?php if (!empty($details)): ?>
                                            <?php
                                                // For consistency with the dashboard dropdown, display common fields in order if present
                                                $orderedKeys = ['title','strand','year','group_number','section','document'];
                                                $hasAnyOrdered = false;
                                                foreach ($orderedKeys as $k) { if (array_key_exists($k, $details)) { $hasAnyOrdered = true; break; } }
                                            ?>
                                            <div class="text-sm text-gray-900 space-y-1 break-words">
                                                <?php if ($hasAnyOrdered): ?>
                                                    <?php foreach ($orderedKeys as $k): if (!array_key_exists($k, $details)) continue; $value = $details[$k]; ?>
                                                        <div>
                                                            <span class="font-medium"><?= htmlspecialchars(str_replace('_', ' ', $k)) ?>:</span>
                                                            <span class="text-gray-600">
                                                                <?php if ($k === 'document' && is_string($value) && $value !== ''): ?>
                                                                    <a href="<?= htmlspecialchars($value) ?>" class="text-blue-600 hover:underline" target="_blank" rel="noopener"><?= htmlspecialchars($value) ?></a>
                                                                <?php else: ?>
                                                                    <?= is_scalar($value) ? htmlspecialchars((string)$value) : htmlspecialchars(json_encode($value)) ?>
                                                                <?php endif; ?>
                                                            </span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <?php // Show any remaining custom keys not in the ordered list ?>
                                                    <?php foreach ($details as $key => $value): if (in_array($key, $orderedKeys, true)) continue; ?>
                                                        <div>
                                                            <span class="font-medium"><?= htmlspecialchars($key) ?>:</span>
                                                            <span class="text-gray-600">
                                                                <?= is_scalar($value) ? htmlspecialchars((string)$value) : htmlspecialchars(json_encode($value)) ?>
                                                            </span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <?php foreach ($details as $key => $value): ?>
                                                        <div>
                                                            <span class="font-medium"><?= htmlspecialchars($key) ?>:</span>
                                                            <span class="text-gray-600">
                                                                <?= is_scalar($value) ? htmlspecialchars((string)$value) : htmlspecialchars(json_encode($value)) ?>
                                                            </span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 pt-2 pb-0 whitespace-nowrap text-sm text-gray-500 align-top text-right leading-tight">
                                        <?= date('M j, Y h:i A', strtotime($log['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="mt-6 flex justify-between items-center">
                    <div class="text-sm text-gray-700">
                        Showing <span class="font-medium"><?= $offset + 1 ?></span> to 
                        <span class="font-medium"><?= min($offset + $per_page, $total_logs) ?></span> of 
                        <span class="font-medium"><?= $total_logs ?></span> logs
                    </div>
                    <div class="flex space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=1<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="px-3 py-1 border rounded hover:bg-gray-100">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="px-3 py-1 border rounded hover:bg-gray-100">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>

                        <div class="flex space-x-1">
                            <?php 
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $start + 4);
                            $start = max(1, $end - 4); // Adjust start if near the end
                            
                            if ($start > 1) echo '<span class="px-3 py-1">...</span>';
                            
                            for ($i = $start; $i <= $end; $i++): 
                            ?>
                                <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                                   class="px-3 py-1 border rounded <?= $i == $page ? 'bg-blue-600 text-white border-blue-600' : 'hover:bg-gray-100' ?>">
                                    <?= $i ?>
                                </a>
                            <?php 
                            endfor; 
                            
                            if ($end < $total_pages) echo '<span class="px-3 py-1">...</span>';
                            ?>
                        </div>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="px-3 py-1 border rounded hover:bg-gray-100">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?page=<?= $total_pages ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="px-3 py-1 border rounded hover:bg-gray-100">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Auto-close success/error messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const messages = document.querySelectorAll('.bg-red-100, .bg-green-100');
            messages.forEach(msg => {
                setTimeout(() => {
                    msg.style.transition = 'opacity 1s';
                    msg.style.opacity = '0';
                    setTimeout(() => msg.remove(), 1000);
                }, 5000);
            });
            // Always start at the top of the page so the Activity Logs are visible
            // Blur any focused element that might auto-scroll the page
            try { document.activeElement && document.activeElement.blur && document.activeElement.blur(); } catch(e) {}
            // Force hash to #top so browser places viewport at the very top
            if (location.hash !== '#top') {
                location.replace('#top');
            }
            const root = document.getElementById('activityRoot');
            const goTop = () => {
                if (root) { root.scrollIntoView({ behavior: 'auto', block: 'start' }); }
                else { window.scrollTo(0, 0); }
            };
            goTop();
            // Some browsers/extensions re-scroll after load; force again shortly after
            setTimeout(goTop, 50);
        });

        // Also handle bfcache restores (e.g., when navigating back/forward)
        window.addEventListener('pageshow', function() {
            try { document.activeElement && document.activeElement.blur && document.activeElement.blur(); } catch(e) {}
            const root = document.getElementById('activityRoot');
            if (root) { root.scrollIntoView({ behavior: 'auto', block: 'start' }); }
            else { window.scrollTo(0, 0); }
            setTimeout(() => {
                if (root) { root.scrollIntoView({ behavior: 'auto', block: 'start' }); }
                else { window.scrollTo(0, 0); }
            }, 50);
        });

        // Clear Activity Logs button behavior
        document.getElementById('clearLogsBtn')?.addEventListener('click', async function(e) {
            e.preventDefault();
            try {
                const confirm = await Swal.fire({
                    title: 'Clear all activity logs?',
                    text: 'This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Yes, clear',
                    cancelButtonText: 'Cancel'
                });
                if (!confirm.isConfirmed) return;

                const res = await fetch('include/clear_activity_logs.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    credentials: 'same-origin',
                    body: 'confirm=1'
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.ok) throw new Error('Failed to clear');

                await Swal.fire({
                    icon: 'success',
                    title: 'Activity logs cleared',
                    timer: 1500,
                    showConfirmButton: false
                });
                // Reload to reflect empty state
                window.location.href = 'activity_logs.php';
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Unable to clear activity logs right now.' });
            }
        });
    </script>
</body>
</html>
