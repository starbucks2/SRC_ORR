<?php
session_start();
include 'db.php';

// Allow both admin and sub-admin to access
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['subadmin_id'])) {
    header("Location: login.php");
    exit();
}

// Prefer status-based archive (2 = Archived). If is_archived column exists, include those too.
try {
    // Try to detect is_archived column
    $hasIsArchived = false;
    try {
        $chk = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'research_submission' AND COLUMN_NAME = 'is_archived'");
        $chk->execute();
        $hasIsArchived = ((int)$chk->fetchColumn() > 0);
    } catch (Throwable $e) { $hasIsArchived = false; }

    if ($hasIsArchived) {
        $stmt = $conn->prepare("SELECT * FROM research_submission WHERE status = 2 OR is_archived = 1");
    } else {
        $stmt = $conn->prepare("SELECT * FROM research_submission WHERE status = 2");
    }
    $stmt->execute();
    $archived = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $archived = [];
}

// Build maps for Section and Group via students table
$studentIdList = [];
foreach ($archived as $p) {
    if (!empty($p['student_id']) && (int)$p['student_id'] > 0) {
        $studentIdList[] = (int)$p['student_id'];
    }
}
$studentSectionMap = [];
$studentGroupMap = [];
if (count($studentIdList) > 0) {
    $studentIdList = array_values(array_unique($studentIdList));
    $ph = implode(',', array_fill(0, count($studentIdList), '?'));
    try {
    // Probe if columns exist
    $hasSection = false; $hasGroup = false;
    try {
        $c1 = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'section'");
        $c1->execute(); $hasSection = ((int)$c1->fetchColumn() > 0);
    } catch (Throwable $e) { $hasSection = false; }
    try {
        $c2 = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'group_number'");
        $c2->execute(); $hasGroup = ((int)$c2->fetchColumn() > 0);
    } catch (Throwable $e) { $hasGroup = false; }

    $selectCols = ['student_id'];
    if ($hasSection) $selectCols[] = 'section';
    if ($hasGroup) $selectCols[] = 'group_number';
    $colList = implode(', ', $selectCols);
    $sStmt = $conn->prepare("SELECT $colList FROM students WHERE student_id IN ($ph)");
    $sStmt->execute($studentIdList);
    foreach ($sStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sid = (int)$row['student_id'];
        $studentSectionMap[$sid] = $row['section'] ?? '';
        $studentGroupMap[$sid] = isset($row['group_number']) ? (int)$row['group_number'] : 0;
    }
} catch (Throwable $e) { /* ignore mapping if fails */ }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Research Papers</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body class="bg-gray-100">
    <div class="flex">
        <?php if (isset($_SESSION['admin_id'])) { include 'admin_sidebar.php'; } else { include 'subadmin_sidebar.php'; } ?>
        <div class="flex-1 p-10">
            <h1 class="text-3xl font-bold text-blue-900 mb-6">📦 Archived Research Papers</h1>
            

            <?php if (count($archived) === 0): ?>
                <p class="text-gray-600">No archived research papers.</p>
            <?php else: ?>
                <div class="overflow-x-auto bg-white shadow-md rounded-lg p-6 mt-6 hidden xl:block">
                    <table class="w-full table-auto border-collapse">
                        <thead>
                            <tr class="bg-blue-100 text-left">
                                <th class="p-3">Title</th>
                                <th class="p-3">Department</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($archived as $paper): ?>
                                <tr class="border-b">
                                    <td class="p-3"><?= htmlspecialchars($paper['title']) ?></td>
                                    <td class="p-3"><?= htmlspecialchars($paper['department'] ?? '') ?></td>
                                    <td class="p-3"><?= ($paper['status'] == 2 || !empty($paper['is_archived'])) ? 'Archived' : (($paper['status'] == 1) ? 'Approved' : 'Pending') ?></td>
                                    <td class="p-3">
                                        <a href="restore_research.php?id=<?= $paper['id'] ?>" 
                                           class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700"
                                           onclick="return confirm('Restore this paper to Research Approvals?')">
                                           ♻️ Restore
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile / Tablet Card List -->
                <div class="xl:hidden grid grid-cols-1 gap-3 mt-4">
                    <?php foreach ($archived as $paper): ?>
                        <div class="bg-white rounded-lg shadow p-4">
                            <h3 class="text-base font-semibold text-gray-900 mb-1">
                                <?= htmlspecialchars($paper['title']) ?>
                            </h3>
                            <p class="text-xs text-gray-500">Department: <span class="font-medium text-gray-700"><?= htmlspecialchars($paper['department'] ?? '') ?></span></p>
                            <p class="text-xs text-gray-500 mt-1">Status: <span class="font-medium <?= ($paper['status']==2||!empty($paper['is_archived'])) ? 'text-amber-600' : (($paper['status']==1)?'text-green-600':'text-gray-600') ?>"><?= ($paper['status'] == 2 || !empty($paper['is_archived'])) ? 'Archived' : (($paper['status'] == 1) ? 'Approved' : 'Pending') ?></span></p>
                            <div class="mt-3">
                                <a href="restore_research.php?id=<?= $paper['id'] ?>"
                                   class="inline-flex items-center bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700"
                                   onclick="return confirm('Restore this paper to Research Approvals?')">
                                    <i class="fas fa-undo mr-1"></i> Restore
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>