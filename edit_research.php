<?php
session_start();
// Allow students or admins/subadmins to edit
$is_student = isset($_SESSION['student_id']);
$is_admin_like = isset($_SESSION['admin_id']) || isset($_SESSION['subadmin_id']);
if (!$is_student && !$is_admin_like) {
    header("Location: login.php");
    exit();
}
include 'db.php';

// Check for the research ID in the URL
$submission_id = $_GET['id'] ?? null;
if (!$submission_id) {
    $_SESSION['error'] = "Invalid request: No research ID provided.";
    header("Location: student_dashboard.php#submissions");
    exit();
}

$student_id = $_SESSION['student_id'] ?? null;

// --- HANDLE THE FORM SUBMISSION (UPDATE LOGIC) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Security check: 
    // - If student: ensure the research belongs to the logged-in student
    // - If admin/subadmin: allow editing any research by id
    if ($is_student) {
        $stmt = $conn->prepare("SELECT image, document FROM research_submission WHERE id = ? AND student_id = ?");
        $stmt->execute([$submission_id, $student_id]);
    } else {
        $stmt = $conn->prepare("SELECT image, document FROM research_submission WHERE id = ?");
        $stmt->execute([$submission_id]);
    }
    $current_submission = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current_submission) {
        $_SESSION['error'] = "You do not have permission to edit this research.";
        header("Location: student_dashboard.php#submissions");
        exit();
    }

    // Get text data from form
    $title = $_POST['title'];
    $year = $_POST['year'];
    $abstract = $_POST['abstract'];
    $members = $_POST['members'];
    $keywords = trim($_POST['keywords'] ?? '');

    // Initialize arrays for building the dynamic SQL query
    $sql_parts = [
        "title = ?",
        "year = ?",
        "abstract = ?",
        "members = ?",
        "keywords = ?"
    ];
    $params = [$title, $year, $abstract, $members, $keywords];
    
    // --- Handle Image Upload ---
    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $image_dir = "images/";
        $image_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $new_image_name = time() . '_' . bin2hex(random_bytes(8)) . '.' . $image_ext;
        $image_target_file = $image_dir . $new_image_name;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $image_target_file)) {
            $sql_parts[] = "image = ?";
            $params[] = $new_image_name;
            // Delete old image if it exists
            if (!empty($current_submission['image']) && file_exists($image_dir . $current_submission['image'])) {
                unlink($image_dir . $current_submission['image']);
            }
        } else {
            $_SESSION['error'] = "Failed to upload new image.";
            header("Location: edit_research.php?id=" . $submission_id);
            exit();
        }
    }

    // --- Handle Document Upload ---
    if (isset($_FILES['document']) && $_FILES['document']['error'] == UPLOAD_ERR_OK) {
        $doc_dir = "uploads/";
        $doc_ext = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
        $new_doc_name = time() . '_' . bin2hex(random_bytes(8)) . '.' . $doc_ext;
        $doc_target_file = $doc_dir . $new_doc_name;

        if (move_uploaded_file($_FILES['document']['tmp_name'], $doc_target_file)) {
            $sql_parts[] = "document = ?";
            $params[] = $doc_target_file; // Store the full path
            // Delete old document if it exists
            if (!empty($current_submission['document']) && file_exists($current_submission['document'])) {
                unlink($current_submission['document']);
            }
        } else {
            $_SESSION['error'] = "Failed to upload new document.";
            header("Location: edit_research.php?id=" . $submission_id);
            exit();
        }
    }

    // Build and execute the final UPDATE statement
    try {
        // Ensure keywords column exists (safety)
        try {
            $ck = $conn->prepare("SHOW COLUMNS FROM research_submission LIKE 'keywords'");
            $ck->execute();
            if ($ck->rowCount() == 0) {
                $conn->exec("ALTER TABLE research_submission ADD COLUMN keywords VARCHAR(255) NULL AFTER abstract");
            }
        } catch (Exception $ie) { /* ignore */ }
        if ($is_student) {
            $sql = "UPDATE research_submission SET " . implode(", ", $sql_parts) . " WHERE id = ? AND student_id = ?";
            $params[] = $submission_id;
            $params[] = $student_id;
        } else {
            $sql = "UPDATE research_submission SET " . implode(", ", $sql_parts) . " WHERE id = ?";
            $params[] = $submission_id;
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        $_SESSION['success'] = "Research project updated successfully!";
        header("Location: student_dashboard.php#submissions");
        exit();

    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating research: " . $e->getMessage();
        header("Location: edit_research.php?id=" . $submission_id);
        exit();
    }
}

// --- FETCH EXISTING DATA TO DISPLAY IN THE FORM ---
try {
    if ($is_student) {
        $stmt = $conn->prepare("SELECT * FROM research_submission WHERE id = ? AND student_id = ?");
        $stmt->execute([$submission_id, $student_id]);
    } else {
        $stmt = $conn->prepare("SELECT * FROM research_submission WHERE id = ?");
        $stmt->execute([$submission_id]);
    }
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$submission) {
        $_SESSION['error'] = "Research not found or you do not have permission to view it.";
        header("Location: student_dashboard.php#submissions");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header("Location: student_dashboard.php#submissions");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Research</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
    @keyframes fade-in {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in { animation: fade-in 0.6s ease-out; }
    </style>
</head>
<body class="min-h-screen bg-gray-100 flex items-start justify-center p-4 sm:p-6 lg:p-8">
    <div class="w-full max-w-full sm:max-w-2xl md:max-w-3xl bg-white shadow-xl p-4 sm:p-6 lg:p-8 rounded-xl border border-gray-200 animate-fade-in">
        <!-- Gradient header strip (mirrors student upload card accent) -->
        <div class="-mt-4 -mx-4 sm:-mx-6 lg:-mx-8 h-2 rounded-t-xl bg-gradient-to-r from-blue-600 to-blue-700 mb-4"></div>
        <h2 class="text-xl sm:text-2xl font-bold text-gray-800 mb-4 flex items-center">
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-blue-50 text-blue-600 mr-3">
                <i class="fas fa-edit"></i>
            </span>
            Edit Research Submission
        </h2>


        <!-- Add enctype for file uploads -->
        <form action="edit_research.php?id=<?= htmlspecialchars($submission_id) ?>" method="POST" enctype="multipart/form-data" class="space-y-5">
            
            <!-- Title & Academic Year -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Research Title <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fas fa-book"></i></span>
                        <input type="text" name="title" value="<?= htmlspecialchars($submission['title']) ?>" placeholder="Enter research title" class="w-full border border-gray-300 pl-10 pr-3 py-2 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm sm:text-base placeholder-gray-400" required>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Academic Year <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fas fa-calendar"></i></span>
                        <?php
                            // Compute Academic Year with June (6) cutoff to mirror student_dashboard
                            $nowYear = (int)date('Y');
                            $nowMonth = (int)date('n');
                            $startYear = ($nowMonth >= 6) ? $nowYear : ($nowYear - 1);
                            $computedSY = 'S.Y. ' . $startYear . '-' . ($startYear + 1);
                        ?>
                        <input type="text" value="<?= htmlspecialchars($computedSY) ?>" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-700 cursor-not-allowed text-sm sm:text-base" readonly>
                        <input type="hidden" name="year" value="<?= htmlspecialchars($computedSY, ENT_QUOTES) ?>">
                    </div>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Introduction <span class="text-red-500">*</span></label>
                <div class="relative">
                    <span class="absolute top-2 left-0 pl-3 text-gray-400"><i class="fas fa-align-left"></i></span>
                    <textarea name="abstract" class="w-full border border-gray-300 pl-10 pr-3 py-2 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm sm:text-base placeholder-gray-400" rows="5" placeholder="Enter a brief summary of your research..." required><?= htmlspecialchars($submission['abstract']) ?></textarea>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Keywords <span class="text-gray-400">(comma-separated)</span></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fas fa-tags"></i></span>
                    <input type="text" name="keywords" value="<?= htmlspecialchars($submission['keywords'] ?? '') ?>" placeholder="e.g., machine learning, climate change, data mining" class="w-full border border-gray-300 pl-10 pr-3 py-2 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm sm:text-base placeholder-gray-400">
                </div>
                <p class="text-xs text-gray-500 mt-1">Add 3–8 keywords separated by commas to improve search visibility.</p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Group Member Name(s) <span class="text-red-500">*</span></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fas fa-users"></i></span>
                    <textarea name="members" class="w-full border border-gray-300 pl-10 pr-3 py-2 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm sm:text-base placeholder-gray-400" rows="3" placeholder="Enter member names separated by commas" required><?= htmlspecialchars($submission['members']) ?></textarea>
                </div>
            </div>

            <!-- File Uploads Enabled -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Poster</label>
                    <input type="file" name="image" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-xs text-gray-500 mt-1">Leave blank to keep the current image.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Research Document (PDF)</label>
                    <input type="file" name="document" accept=".pdf" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-xs text-gray-500 mt-1">Leave blank to keep the current document.</p>
                </div>
            </div>
            
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-4 gap-3 pt-4 border-t mt-4">
                <a href="student_dashboard.php#submissions" class="w-full sm:w-auto text-center px-6 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                    Cancel
                </a>
                <button type="submit" class="w-full sm:w-auto px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center">
                    <i class="fas fa-save mr-2"></i>
                    Update Changes
                </button>
            </div>
        </form>
    </div>
</body>
</html>
