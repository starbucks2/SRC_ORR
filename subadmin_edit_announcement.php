<?php
session_start();
include 'db.php';

// Allow both admin and sub-admin to access
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['subadmin_id'])) {
    header("Location: login.php");
    exit();
}

// Permission check for sub-admins
$can_manage_announcements = true;
if (isset($_SESSION['subadmin_id'])) {
    $can_manage_announcements = false;
    $permissions = json_decode($_SESSION['permissions'] ?? '[]', true);
    if (in_array('manage_announcements', $permissions)) {
        $can_manage_announcements = true;
    }
    if (!$can_manage_announcements) {
        $_SESSION['error'] = "You do not have permission to edit announcements.";
        header("Location: subadmin_announcements.php");
        exit();
    }
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No announcement ID provided.";
    header("Location: announcements.php");
    exit();
}

$id = $_GET['id'];

// Fetch announcement
$stmt = $conn->prepare("SELECT * FROM announcements WHERE id = ?");
$stmt->execute([$id]);
$announcement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$announcement) {
    $_SESSION['error'] = "Announcement not found.";
    header("Location: announcements.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Announcement</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-10">
    <div class="max-w-3xl mx-auto bg-white shadow-md rounded-lg p-8">
        <h2 class="text-2xl font-bold text-blue-900 mb-6">✏️ Edit Announcement</h2>

        <form action="update_announcement.php" method="POST">
            <input type="hidden" name="id" value="<?= $announcement['id']; ?>">

            <div class="mb-4">
                <label class="block text-gray-700 font-medium mb-2">Title</label>
                <input type="text" name="title" value="<?= htmlspecialchars($announcement['title']); ?>" required class="w-full border px-4 py-2 rounded">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 font-medium mb-2">Opens At</label>
                <input type="datetime-local" name="open_at" value="<?= !empty($announcement['open_at']) ? date('Y-m-d\TH:i', strtotime($announcement['open_at'])) : '' ?>" required class="w-full border px-4 py-2 rounded">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 font-medium mb-2">Deadline</label>
                <input type="datetime-local" name="deadline" value="<?= date('Y-m-d\TH:i', strtotime($announcement['deadline'])); ?>" required class="w-full border px-4 py-2 rounded">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 font-medium mb-2">Content</label>
                <textarea name="content" rows="5" required class="w-full border px-4 py-2 rounded"><?= htmlspecialchars($announcement['content']); ?></textarea>
            </div>

            <div class="flex space-x-4">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Update</button>
                <a href="subadmin_announcements.php" class="bg-gray-400 text-white px-6 py-2 rounded hover:bg-gray-500">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
