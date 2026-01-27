<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';
$stmt = $conn->prepare("SELECT * FROM research_submission WHERE student_id = ?");
$stmt->execute([$_SESSION['student_id']]);
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
$strand = $_SESSION['strand'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Research Submissions | BNHS Research Repository</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
<div class="flex-1 p-4 sm:p-6 lg:p-8">
    <section class="bg-gradient-to-br from-[#2563eb] via-[#1e40af] to-[#1e3a8a] text-white p-8 sm:p-10 rounded-2xl shadow-2xl card-hover transition-all duration-300 max-w-4xl mx-auto mt-12">
        <div class="flex items-center mb-8">
            <i class="fas fa-list-alt text-3xl text-white mr-4"></i>
            <h3 class="text-3xl font-extrabold text-white">My Research Submissions</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse bg-white bg-opacity-10 rounded-xl overflow-hidden">
                <thead>
                    <tr class="bg-white bg-opacity-20 border-b-2 border-blue-200">
                        <th class="text-left px-4 py-3 text-sm font-semibold text-blue-100">Title</th>
                        <th class="text-left px-4 py-3 text-sm font-semibold text-blue-100">Academic Year</th>
                        <th class="text-left px-4 py-3 text-sm font-semibold text-blue-100">Course</th>
                        <th class="text-left px-4 py-3 text-sm font-semibold text-blue-100">Group No.</th>
                        <th class="text-left px-4 py-3 text-sm font-semibold text-blue-100">Section</th>
                        <th class="text-left px-4 py-3 text-sm font-semibold text-blue-100">Status</th>
                        <th class="text-left px-4 py-3 text-sm font-semibold text-blue-100">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($submissions) > 0): ?>
                        <?php foreach ($submissions as $submission): ?>
                            <tr class="border-b border-blue-200 hover:bg-white hover:bg-opacity-20 transition-colors duration-150">
                                <td class="px-4 py-3 text-sm font-medium text-white"><?php echo htmlspecialchars($submission['title']); ?></td>
                                <td class="px-4 py-3 text-sm text-blue-100"><?php echo htmlspecialchars($submission['year']); ?></td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-200 bg-opacity-40 text-white">
                                        <?php echo htmlspecialchars($submission['strand'] ?? $strand); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-blue-100">
                                    <?php echo isset($submission['group_number']) && $submission['group_number'] !== '' ? htmlspecialchars($submission['group_number']) : '—'; ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-blue-100">
                                    <?php 
                                        $secVal = isset($submission['section']) && $submission['section'] !== '' ? $submission['section'] : ($_SESSION['section'] ?? '');
                                        echo $secVal !== '' ? htmlspecialchars($secVal) : '—';
                                    ?>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="status-badge <?php echo $submission['status'] == 1 ? 'status-approved' : 'status-pending'; ?>">
                                        <?php echo $submission['status'] == 1 ? 'Approved' : 'Pending'; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="flex items-center space-x-3">
                                        <a href="<?php echo htmlspecialchars($submission['document']); ?>" class="text-blue-200 hover:text-white transition-colors duration-200 flex items-center action-btn" target="_blank">
                                            <i class="fas fa-file-pdf mr-1"></i>
                                            View
                                        </a>
                                        <?php if ($submission['status'] == 0): ?>
                                            <a href="edit_research.php?id=<?php echo $submission['id']; ?>" class="text-yellow-200 hover:text-yellow-400 transition-colors duration-200 flex items-center action-btn">
                                                <i class="fas fa-edit mr-1"></i>
                                                Edit
                                            </a>
                                            <a href="delete_research.php?id=<?php echo $submission['id']; ?>" class="text-red-300 hover:text-red-500 transition-colors duration-200 flex items-center action-btn" onclick="return confirm('Are you sure you want to delete this research? This action cannot be undone.');">
                                                <i class="fas fa-trash-alt mr-1"></i>
                                                Delete
                                            </a>
                                        <?php else: ?>
                                            <span class="text-blue-200 text-xs">[Approved]</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-8 text-blue-100">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-folder-open text-4xl mb-3 text-blue-200"></i>
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
</body>
</html>
