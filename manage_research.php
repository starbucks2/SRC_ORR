<?php
session_start();
include 'db.php';

// Only allow admin or subadmin
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['subadmin_id'])) {
    $_SESSION['error'] = "You must be logged in as an admin or subadmin.";
    header("Location: login.php");
    exit();
}

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM research_submission WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['success'] = "Research deleted successfully.";
    header("Location: manage_research.php");
    exit();
}

// Fetch all research submissions
$stmt = $conn->query("SELECT rs.*, COALESCE(s.firstname, 'Admin Upload') as uploader, COALESCE(s.strand, 'Admin') as uploader_strand FROM research_submission rs LEFT JOIN students s ON rs.student_id = s.student_id ORDER BY rs.submission_date DESC");
$researches = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Research - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include 'admin_sidebar.php'; ?>
    <main class="p-6 max-w-7xl mx-auto">
        <h1 class="text-3xl font-bold mb-6 text-blue-900 flex items-center"><i class="fas fa-database mr-3"></i>Manage Research</h1>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        <div class="overflow-x-auto bg-white rounded-xl shadow p-4">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Uploader</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Strand</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Keywords</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Year</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($researches as $research): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2 font-semibold text-blue-800"><?= htmlspecialchars($research['title']) ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($research['uploader']) ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($research['uploader_strand']) ?></td>
                        <td class="px-4 py-2 text-sm">
                            <?php if (!empty($research['keywords'])): ?>
                                <?php $kwList = array_filter(array_map('trim', preg_split('/\s*,\s*/', (string)$research['keywords']))); ?>
                                <div class="flex flex-wrap gap-1">
                                    <?php foreach ($kwList as $kw): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-100">
                                            <i class="fas fa-tag mr-1 text-xs"></i><?= htmlspecialchars($kw) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-gray-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2"><?= htmlspecialchars($research['year']) ?></td>
                        <td class="px-4 py-2">
                            <?php
                                if ($research['status'] == 1) echo '<span class="text-green-600 font-bold">Approved</span>';
                                elseif ($research['status'] == 0) echo '<span class="text-yellow-600 font-bold">Pending</span>';
                                elseif ($research['status'] == 2) echo '<span class="text-red-600 font-bold">Rejected</span>';
                                else echo '<span class="text-gray-600">Unknown</span>';
                            ?>
                        </td>
                        <td class="px-4 py-2 flex gap-2">
                            <a href="edit_research.php?id=<?= $research['id'] ?>" class="text-blue-600 hover:text-blue-900 font-bold"><i class="fas fa-edit"></i> Edit</a>
                            <a href="manage_research.php?delete=<?= $research['id'] ?>" class="text-red-600 hover:text-red-900 font-bold" onclick="return confirm('Are you sure you want to delete this research?');"><i class="fas fa-trash"></i> Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (count($researches) === 0): ?>
                <div class="text-center text-gray-500 py-8">No research found.</div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
