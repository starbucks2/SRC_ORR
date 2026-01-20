<?php
session_start();
include 'db.php';

// Allow both admin and sub-admin to access
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['subadmin_id'])) {
    header("Location: login.php");
    exit();
}

// Detect if research_submission.is_archived exists
try {
    $chk = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'research_submission' AND COLUMN_NAME = 'is_archived'");
    $chk->execute();
    $hasIsArchived = ((int)$chk->fetchColumn() > 0);
} catch (Throwable $_) { $hasIsArchived = false; }

// Optional: scope by subadmin department
$deptFilterSql = '';
$deptFilterVals = [];
if (isset($_SESSION['subadmin_id'])) {
    // Detect which role columns exist in employees
    try {
        $c1 = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'role'");
        $c1->execute();
        $hasRoleCol = ((int)$c1->fetchColumn() > 0);
    } catch (Throwable $_) { $hasRoleCol = false; }
    try {
        $c2 = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'employee_type'");
        $c2->execute();
        $hasEmpTypeCol = ((int)$c2->fetchColumn() > 0);
    } catch (Throwable $_) { $hasEmpTypeCol = false; }
    $wanted = "('RESEARCH_ADVISER','FACULTY')";
    if ($hasRoleCol || $hasEmpTypeCol) {
        $roleWhere = $hasRoleCol && $hasEmpTypeCol
            ? "(UPPER(REPLACE(TRIM(role),' ','_')) IN $wanted OR UPPER(REPLACE(TRIM(employee_type),' ','_')) IN $wanted)"
            : ($hasRoleCol
                ? "UPPER(REPLACE(TRIM(role),' ','_')) IN $wanted"
                : "UPPER(REPLACE(TRIM(employee_type),' ','_')) IN $wanted");
        try {
            $qDept = $conn->prepare("SELECT department FROM employees WHERE employee_id = ? AND $roleWhere LIMIT 1");
            $qDept->execute([$_SESSION['subadmin_id']]);
            $dept = (string)($qDept->fetchColumn() ?: '');
            if ($dept !== '') {
                $deptFilterSql = " AND department LIKE ?";
                $deptFilterVals[] = '%'.$dept.'%';
            }
        } catch (Throwable $_) { /* ignore filter if lookup fails */ }
    }
}

$whereArchived = $hasIsArchived ? "(status = 2 OR COALESCE(is_archived,0) = 1)" : "status = 2";
$sql = "SELECT * FROM research_submission WHERE $whereArchived" . $deptFilterSql;
$stmt = $conn->prepare($sql);
$stmt->execute($deptFilterVals);
$archived = $stmt->fetchAll(PDO::FETCH_ASSOC);

// No need to map section/group; display department only
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
        <?php include 'subadmin_sidebar.php'; ?>
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