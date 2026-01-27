<?php
session_start();
include 'db.php';

// Check if the admin is logged in
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

// Fetch Grade 12 students only, include section, lrn, group_number and profile_pic
$stmt = $conn->query("SELECT id, fullname, email, lrn, grade, strand, section, group_number, status, research_file, profile_pic FROM students WHERE (grade = '12' OR grade = 'Grade 12' OR grade LIKE '%12%')");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check for success message
$successMessage = isset($_GET['success']) ? $_GET['success'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Students</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background-image: url('bnhsbackground.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        .overlay {
            background-color: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            padding: 20px;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="overlay max-w-6xl w-full mx-auto shadow-md">
        <!-- Alert Message -->
        <?php if ($successMessage): ?>
            <div class="mb-4 p-3 bg-green-100 text-green-700 border border-green-400 rounded">
                <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>

        <!-- Logo Centered -->
        <div class="flex justify-center mb-4">
            <img src="Bnhslogo.jpg" alt="Logo" class="w-20 h-20">
        </div>
        
        <h2 class="text-2xl font-bold mb-4 text-center text-gray-800">Student List</h2>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse border border-gray-300 text-sm sm:text-base bg-white">
                <thead>
                    <tr class="bg-gray-200 text-left">
                        <th class="border p-2">Profile Picture</th>
                        <th class="border p-2">Full Name</th>
                        <th class="border p-2">Email</th>
                        <th class="border p-2">Grade</th>
                        <th class="border p-2">Strand</th>
                        <th class="border p-2">Section</th>
                        <th class="border p-2">Status</th>
                        <th class="border p-2">File Name</th>
                        <th class="border p-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr class="hover:bg-gray-100">
                            <td class="border p-2 text-center">
                                <?php 
                                    $pic = $student['profile_pic'] ?? '';
                                    $profilePicPath = (!empty($pic) && file_exists('images/' . $pic))
                                        ? 'images/' . htmlspecialchars($pic)
                                        : 'images/default.jpg';
                                ?>
                                <img src="<?= $profilePicPath ?>" alt="Profile Pic" class="w-12 h-12 rounded-full mx-auto border border-gray-300">
                            </td>
                            <td class="border p-2"><?= htmlspecialchars($student['fullname']) ?></td>
                            <td class="border p-2"><?= htmlspecialchars($student['email']) ?></td>
                            <td class="border p-2 text-center"><?= htmlspecialchars($student['grade']) ?></td>
                            <td class="border p-2"><?= htmlspecialchars($student['strand']) ?></td>
                            <td class="border p-2"><?= htmlspecialchars($student['section'] ?? '-') ?></td>
                            <td class="border p-2">
                                <span class="px-2 py-1 rounded 
                                    <?= $student['status'] == 'Verified' ? 'bg-green-200 text-green-800' : 
                                       ($student['status'] == 'Rejected' ? 'bg-red-200 text-red-800' : 'bg-yellow-200 text-yellow-800') ?>">
                                    <?= htmlspecialchars($student['status']) ?>
                                </span>
                            </td>
                            <td class="border p-2">
                                <?php if (!empty($student['research_file'])): ?>
                                    <a href="<?= htmlspecialchars($student['research_file']) ?>" target="_blank" class="text-blue-500 hover:text-blue-700">
                                        <?= htmlspecialchars(basename($student['research_file'])) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-500">No file uploaded</span>
                                <?php endif; ?>
                            </td>
                            <td class="border p-2 text-center">
                                <form method="POST" action="verify_student.php">
                                    <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                    <div class="flex flex-col sm:flex-row items-center gap-2 justify-center">
                                        <button type="button"
                                                onclick="showAdminStudentModal('<?= htmlspecialchars(addslashes($student['fullname'])) ?>','<?= htmlspecialchars(addslashes($student['email'])) ?>','<?= htmlspecialchars(addslashes($student['lrn'] ?? '')) ?>','<?= htmlspecialchars(addslashes($student['grade'])) ?>','<?= htmlspecialchars(addslashes($student['strand'])) ?>','<?= htmlspecialchars(addslashes($student['section'] ?? '')) ?>','<?= (int)($student['group_number'] ?? 0) ?>','<?= htmlspecialchars(addslashes($student['status'])) ?>','<?= htmlspecialchars(addslashes($student['profile_pic'] ?? '')) ?>','<?= htmlspecialchars(addslashes($student['research_file'] ?? '')) ?>')"
                                                class="bg-indigo-600 text-white px-3 py-1 rounded hover:bg-indigo-700">
                                            View
                                        </button>
                                    <select name="status" class="border p-1 rounded">
                                        <option value="Verified" <?= $student['status'] == 'Verified' ? 'selected' : '' ?>>Verified</option>
                                        <option value="Rejected" <?= $student['status'] == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                                    </select>
                                    <button type="submit" class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">Update</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <a href="admin_dashboard.php" class="block mt-4 text-blue-500 hover:text-blue-700 text-center">Back to Dashboard</a>
    </div>
    <!-- Profile Modal -->
    <div id="adminStudentModal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center z-50" onclick="closeAdminStudentModal(event)">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6 relative" onclick="event.stopPropagation()">
            <button onclick="hideAdminStudentModal()" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            <div class="flex flex-col items-center text-center">
                <img id="admModalPic" src="" alt="Profile Picture" class="w-28 h-28 rounded-full border-4 border-blue-500 mb-4 object-cover shadow-lg">
                <h3 id="admModalName" class="text-xl font-bold text-gray-900 mb-1"></h3>
                <p id="admModalEmail" class="text-gray-700 mb-1"></p>
                <p id="admModalLRN" class="text-gray-700 mb-1"></p>
                <p id="admModalGrade" class="text-gray-700 mb-1"></p>
                <p id="admModalStrand" class="text-gray-700 mb-1"></p>
                <p id="admModalSection" class="text-gray-700 mb-1"></p>
                <p id="admModalGroup" class="text-gray-700 mb-1"></p>
                <p id="admModalStatus" class="text-gray-700 mb-1"></p>
                <div id="admModalFile" class="mt-2"></div>
            </div>
        </div>
    </div>

    <script>
    function showAdminStudentModal(fullname, email, lrn, grade, strand, section, groupNumber, status, profilePic, researchFile){
        document.getElementById('admModalName').textContent = fullname || '';
        document.getElementById('admModalEmail').textContent = 'Email: ' + (email || '');
        document.getElementById('admModalLRN').textContent = 'LRN: ' + (lrn || '');
        document.getElementById('admModalGrade').textContent = 'Grade: ' + (grade || '');
        document.getElementById('admModalStrand').textContent = 'Strand: ' + (strand || '');
        document.getElementById('admModalSection').textContent = 'Section: ' + (section || '');
        document.getElementById('admModalGroup').textContent = 'Group: ' + (parseInt(groupNumber) > 0 ? ('Group ' + parseInt(groupNumber)) : '-');
        document.getElementById('admModalStatus').textContent = 'Status: ' + (status || '');

        var picPath = 'images/default.jpg';
        if (profilePic) {
            var test = 'images/' + profilePic;
            picPath = test;
        }
        document.getElementById('admModalPic').src = picPath;

        var fileDiv = document.getElementById('admModalFile');
        if (researchFile) {
            var safe = researchFile;
            fileDiv.innerHTML = '<a href="' + safe + '" target="_blank" class="text-blue-600 hover:text-blue-800">View Research File</a>';
        } else {
            fileDiv.innerHTML = '<span class="text-gray-500">No research file</span>';
        }

        var modal = document.getElementById('adminStudentModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    function hideAdminStudentModal(){
        var modal = document.getElementById('adminStudentModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    function closeAdminStudentModal(e){
        if (e.target && e.target.id === 'adminStudentModal') hideAdminStudentModal();
    }
    </script>
</body>
</html>