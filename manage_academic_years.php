<?php
// Manage Academic/School Year page (admin only)
session_start();
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['error'] = 'You must be logged in as an admin.';
    header('Location: login.php');
    exit();
}
require_once __DIR__ . '/db.php';

// Ensure table exists
$conn->exec("CREATE TABLE IF NOT EXISTS academic_years (
    id INT AUTO_INCREMENT PRIMARY KEY,
    span VARCHAR(15) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$flash = ['success' => null, 'error' => null];

function valid_span($span) {
    // Expect format YYYY-YYYY and end = start + 1
    if (!preg_match('/^(\\d{4})-(\\d{4})$/', $span, $m)) return false;
    return ((int)$m[2] === (int)$m[1] + 1);
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if ($action === 'add') {
            $span = trim($_POST['span'] ?? '');
            if (!valid_span($span)) {
                $flash['error'] = 'Invalid span. Use format YYYY-YYYY, where the end year is start year + 1.';
            } else {
                $stmt = $conn->prepare('INSERT IGNORE INTO academic_years(span, is_active) VALUES(?, 1)');
                $stmt->execute([$span]);
                if ($stmt->rowCount() > 0) {
                    $flash['success'] = 'Year added.';
                } else {
                    $flash['error'] = 'Year already exists.';
                }
            }
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $cur = $conn->prepare('SELECT is_active FROM academic_years WHERE id = ?');
            $cur->execute([$id]);
            $state = $cur->fetchColumn();
            if ($state === false) {
                $flash['error'] = 'Year not found.';
            } else {
                $new = ((int)$state) ? 0 : 1;
                $upd = $conn->prepare('UPDATE academic_years SET is_active = ? WHERE id = ?');
                $upd->execute([$new, $id]);
                $flash['success'] = $new ? 'Year activated.' : 'Year deactivated.';
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $del = $conn->prepare('DELETE FROM academic_years WHERE id = ?');
            $del->execute([$id]);
            $flash['success'] = 'Year deleted.';
        }
    }
} catch (Throwable $e) {
    $flash['error'] = 'Action failed: ' . htmlspecialchars($e->getMessage());
}

// Fetch all years
$years = $conn->query('SELECT id, span, is_active, created_at FROM academic_years ORDER BY SUBSTRING_INDEX(span, "-", 1) DESC')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Academic/School Year</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-100 flex">
  <?php include 'admin_sidebar.php'; ?>
  <main class="flex-1 p-4 sm:p-6 lg:p-10 w-full max-w-6xl mx-auto">
    <div class="bg-white rounded-2xl shadow p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-calendar-alt mr-2 text-blue-600"></i>Manage Academic/School Year</h1>
        <a href="admin_upload_research.php" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md"><i class="fas fa-upload mr-2"></i>Go to Upload</a>
      </div>

      <?php if ($flash['success']): ?>
        <div class="mb-4 p-3 bg-green-50 text-green-800 border border-green-200 rounded"><?= htmlspecialchars($flash['success']) ?></div>
      <?php endif; ?>
      <?php if ($flash['error']): ?>
        <div class="mb-4 p-3 bg-red-50 text-red-800 border border-red-200 rounded"><?= htmlspecialchars($flash['error']) ?></div>
      <?php endif; ?>

      <form method="post" class="mb-8 grid grid-cols-1 sm:grid-cols-3 gap-3">
        <input type="hidden" name="action" value="add">
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">New Year Span (YYYY-YYYY)</label>
          <input type="text" name="span" placeholder="e.g., 2026-2027" class="w-full px-3 py-2 border rounded-md" required>
          <p class="text-xs text-gray-500 mt-1">Note: S.Y. vs A.Y. is applied automatically in Upload based on Department.</p>
        </div>
        <div class="flex items-end">
          <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md"><i class="fas fa-plus mr-2"></i>Add</button>
        </div>
      </form>

      <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Span</th>
              <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Active</th>
              <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Created</th>
              <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <?php if (empty($years)): ?>
              <tr><td colspan="4" class="px-4 py-4 text-center text-gray-500">No years yet.</td></tr>
            <?php else: foreach ($years as $yr): ?>
              <tr>
                <td class="px-4 py-2 font-medium text-gray-800"><?= htmlspecialchars($yr['span']) ?></td>
                <td class="px-4 py-2">
                  <?php if ((int)$yr['is_active'] === 1): ?>
                    <span class="inline-flex items-center px-2 py-0.5 text-xs rounded bg-green-100 text-green-800"><i class="fas fa-check mr-1"></i>Active</span>
                  <?php else: ?>
                    <span class="inline-flex items-center px-2 py-0.5 text-xs rounded bg-gray-100 text-gray-700"><i class="fas fa-pause mr-1"></i>Inactive</span>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-2 text-sm text-gray-500"><?= htmlspecialchars($yr['created_at']) ?></td>
                <td class="px-4 py-2 space-x-2">
                  <form method="post" class="inline">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int)$yr['id'] ?>">
                    <button class="px-3 py-1 rounded text-white <?= (int)$yr['is_active'] ? 'bg-gray-500 hover:bg-gray-600' : 'bg-green-600 hover:bg-green-700' ?>" type="submit">
                      <?= (int)$yr['is_active'] ? '<i class=\'fas fa-eye-slash mr-1\'></i>Deactivate' : '<i class=\'fas fa-eye mr-1\'></i>Activate' ?>
                    </button>
                  </form>
                  <form method="post" class="inline" onsubmit="return confirm('Delete this year?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$yr['id'] ?>">
                    <button class="px-3 py-1 rounded bg-red-600 hover:bg-red-700 text-white" type="submit"><i class="fas fa-trash mr-1"></i>Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <p class="text-xs text-gray-500 mt-4">Any active year you add here will immediately appear in the Upload Research form as either A.Y. or S.Y., depending on the selected department.</p>
    </div>
  </main>
</body>
</html>
