<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';
// Get student's strand
$stmtStrand = $conn->prepare("SELECT strand FROM students WHERE student_id = ?");
$stmtStrand->execute([$_SESSION['student_id']]);
$studentStrand = $stmtStrand->fetchColumn();

// Fetch announcements strictly for student's strand (posted by matching sub-admin strand)
$query = "SELECT a.*
          FROM announcements a
          WHERE (a.strand = ? OR a.strand IS NULL OR a.strand = '')
          ORDER BY a.created_at DESC
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute([$studentStrand]);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Announcements | BNHS Research Repository</title>
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
    <section class="bg-gradient-to-br from-blue-700 to-blue-900 p-5 sm:p-6 rounded-xl shadow-lg card-hover transition-all duration-300 max-w-2xl mx-auto mt-8">
        <div class="flex items-center mb-5">
            <i class="fas fa-bullhorn text-2xl text-yellow-300 mr-3"></i>
            <h3 class="text-2xl font-bold text-white">Important Announcements</h3>
        </div>
        <?php if (count($announcements) > 0): ?>
            <div class="space-y-4">
                <?php foreach ($announcements as $announcement): 
                    $deadline = new DateTime($announcement['deadline']);
                    $now = new DateTime();
                    $isPastDeadline = $deadline < $now;
                ?>
                <div class="p-4 rounded-lg border-l-4 <?php echo $isPastDeadline ? 'bg-blue-800/60 border-gray-300' : 'bg-blue-600/60 border-yellow-400'; ?>">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start mb-2">
                        <div>
                            <h4 class="text-lg font-semibold text-white"><?php echo htmlspecialchars($announcement['title']); ?></h4>
                            <span class="text-sm text-blue-200">For: <?php echo htmlspecialchars($announcement['strand'] ?: 'All'); ?> Students</span>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $isPastDeadline ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?> mt-1 sm:mt-0">
                            <i class="fas fa-clock mr-1.5"></i>
                            <?php echo $isPastDeadline ? 'Deadline Passed' : 'Active'; ?>
                        </span>
                    </div>
                    <p class="text-blue-100 mb-2 text-sm sm:text-base"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                    <div class="flex flex-col sm:flex-row sm:justify-between text-xs text-blue-200">
                        <span>Posted: <?php echo date('M j, Y', strtotime($announcement['created_at'])); ?></span>
                        <span>Deadline: <?php echo date('M j, Y g:i A', strtotime($announcement['deadline'])); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-8">
                <i class="fas fa-bell-slash text-4xl text-yellow-200 mb-3"></i>
                <p class="text-blue-100">No announcements available at the moment.</p>
            </div>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
