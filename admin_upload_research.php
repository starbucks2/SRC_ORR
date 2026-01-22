<?php
// Centralized secure session initialization (PHP 8.1+ safe)
include __DIR__ . '/include/session_init.php';

// Allow admin or sub-admin with permission
$is_admin = isset($_SESSION['admin_id']);
$is_subadmin = isset($_SESSION['subadmin_id']);
$can_upload = false;

if ($is_admin) {
    $can_upload = true;
} elseif ($is_subadmin) {
    $permissions = json_decode($_SESSION['permissions'] ?? '[]', true);
    if (in_array('upload_research', $permissions)) {
        $can_upload = true;
    }
}

if (!$can_upload) {
    $_SESSION['error'] = "Unauthorized access.";
    header("Location: " . ($is_subadmin ? "subadmin_dashboard.php" : "login.php"));
    exit();
}

// Handle form submission
$message = '';
$message_type = ''; // 'success' or 'error'

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    include 'db.php';

    // Safely read inputs to avoid undefined index notices on PHP 8.1+
    $title = isset($_POST['title']) ? trim((string)$_POST['title']) : '';
    $year = isset($_POST['year']) ? trim((string)$_POST['year']) : '';
    $abstract = isset($_POST['abstract']) ? trim((string)$_POST['abstract']) : '';
    $author = isset($_POST['author']) ? trim((string)$_POST['author']) : '';
    // Use department field (replaces legacy 'strand')
    $department = isset($_POST['department']) ? trim((string)$_POST['department']) : '';
    $course_strand = isset($_POST['course_strand']) ? trim((string)$_POST['course_strand']) : '';
    $keywords = isset($_POST['keywords']) ? trim((string)$_POST['keywords']) : '';
    $status = 1; // Approved

    // Default department to session department for sub-admins if not provided
    if ($department === '' && isset($_SESSION['subadmin_id']) && !empty($_SESSION['department'])) {
        $department = (string)$_SESSION['department'];
    }

    // Validate required fields with better feedback
    $missing = [];
    if ($title === '') { $missing[] = 'Title'; }
    if ($year === '') { $missing[] = 'Academic/School Year'; }
    if ($abstract === '') { $missing[] = 'Abstract'; }
    if ($author === '') { $missing[] = 'Author(s)'; }
    if ($department === '') { $missing[] = 'Department'; }
    if (!empty($missing)) {
        $message = 'Please fill the following: ' . implode(', ', $missing) . '.';
        $message_type = 'error';
    } else {
        $image = '';
        $document = '';

        // Validate Department and Course/Strand against DB (Option C hybrid)
        try {
            require_once 'db.php';
            $deptStmt = $conn->prepare("SELECT department_id, name, code, is_active FROM departments WHERE name = ? LIMIT 1");
            $deptStmt->execute([$department]);
            $deptRow = $deptStmt->fetch(PDO::FETCH_ASSOC);
            if (!$deptRow || (int)$deptRow['is_active'] !== 1) {
                $message = "Invalid department selected.";
                $message_type = 'error';
            } else {
                $isSHS = (strtolower($deptRow['name']) === 'senior high school' || strtolower((string)$deptRow['code']) === 'shs');
                if ($isSHS) {
                    // Validate strand by name
                    $sStmt = $conn->prepare("SELECT COUNT(*) FROM strands WHERE strand = ?");
                    $sStmt->execute([$course_strand]);
                    if ((int)$sStmt->fetchColumn() === 0) {
                        $message = "Invalid strand selected.";
                        $message_type = 'error';
                    }
                } else {
                    // Validate course belongs to department
                    $cStmt = $conn->prepare("SELECT COUNT(*) FROM courses WHERE department_id = ? AND course_name = ? AND is_active = 1");
                    $cStmt->execute([(int)$deptRow['department_id'], $course_strand]);
                    if ((int)$cStmt->fetchColumn() === 0) {
                        $message = "Invalid course selected for the chosen department.";
                        $message_type = 'error';
                    }
                }
            }
        } catch (Throwable $e) {
            // If validation fails due to DB error, provide message
            if (!$message) { $message = 'Validation error: ' . htmlspecialchars($e->getMessage()); $message_type = 'error'; }
        }

        // Prepare upload directories
        $imgDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'research_images';
        $docDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'research_documents';
        if (!is_dir($imgDir)) {@mkdir($imgDir, 0775, true);} // suppress warnings if concurrent
        if (!is_dir($docDir)) {@mkdir($docDir, 0775, true);} // suppress warnings if concurrent

        // Upload image (optional)
        if (!empty($_FILES['image']['name'])) {
            if (isset($_FILES['image']['error']) && $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                $message = 'Image upload error (code ' . (int)$_FILES['image']['error'] . ').';
                $message_type = 'error';
            } else {
                $imgName = $_FILES['image']['name'];
                $imgTmp = $_FILES['image']['tmp_name'];
                $imgSize = (int)$_FILES['image']['size'];
                $imgExt = strtolower(pathinfo($imgName, PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];

                // MIME check for image
                $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
                $mime = $finfo ? finfo_file($finfo, $imgTmp) : null;
                if ($finfo) finfo_close($finfo);
                $allowedMime = ['image/jpeg','image/png','image/gif'];

                if (in_array($imgExt, $allowedExts) && ($mime === null || in_array($mime, $allowedMime)) && $imgSize < 10 * 1024 * 1024) {
                    $destRel = 'uploads/research_images/' . uniqid('img_') . '.' . $imgExt;
                    $destAbs = $imgDir . DIRECTORY_SEPARATOR . basename($destRel);
                    if (!move_uploaded_file($imgTmp, $destAbs)) {
                        $message = 'Failed to save image to disk.';
                        $message_type = 'error';
                    } else {
                        // store relative web path
                        $image = $destRel;
                    }
                } else {
                    $message = "Invalid image file. Use JPG, PNG, GIF under 10MB.";
                    $message_type = 'error';
                }
            }
        }

        // Upload PDF document (optional)
        if (!empty($_FILES['document']['name'])) {
            if (isset($_FILES['document']['error']) && $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
                $message = 'Document upload error (code ' . (int)$_FILES['document']['error'] . ').';
                $message_type = 'error';
            } else {
                $docName = $_FILES['document']['name'];
                $docTmp = $_FILES['document']['tmp_name'];
                $docSize = (int)$_FILES['document']['size'];
                $docExt = strtolower(pathinfo($docName, PATHINFO_EXTENSION));

                // MIME check for PDF
                $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
                $mime = $finfo ? finfo_file($finfo, $docTmp) : null;
                if ($finfo) finfo_close($finfo);

                if ($docExt === 'pdf' && ($mime === null || $mime === 'application/pdf') && $docSize < 25 * 1024 * 1024) {
                    // Check if a research with same title exists in books (case-insensitive)
                    $check_stmt = $conn->prepare("SELECT book_id FROM books WHERE LOWER(TRIM(title)) = LOWER(TRIM(?)) AND status = 1");
                    $check_stmt->execute([$title]);
                    if ($check_stmt->rowCount() > 0) {
                        $message = "A research with this title already exists in the repository. Please use a different title.";
                        $message_type = 'error';
                    } else {
                        // Create unique filename using timestamp and sanitized title
                        $safe_title = preg_replace('/[^a-z0-9]+/', '-', strtolower($title));
                        $fileBase = time() . '_' . $safe_title . '_' . bin2hex(random_bytes(4));
                        $destRel = 'uploads/research_documents/' . $fileBase . '.pdf';
                        $destAbs = $docDir . DIRECTORY_SEPARATOR . basename($destRel);
                        if (!move_uploaded_file($docTmp, $destAbs)) {
                            $message = "Failed to upload document to disk.";
                            $message_type = 'error';
                        } else {
                            $document = $destRel; // store relative web path
                        }
                    }
                } else {
                    $message = "Only PDF files under 25MB allowed.";
                    $message_type = 'error';
                }
            }
        }

        // Insert into database if no errors
        if (!$message) {
            try {
                // For admin uploads, set student_id to NULL and adviser_id to current admin
                $adviser_id = $_SESSION['admin_id'] ?? null;
                $stmt = $conn->prepare("INSERT INTO books (student_id, adviser_id, title, year, abstract, keywords, authors, department, course_strand, document, image, status) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$adviser_id, $title, $year, $abstract, $keywords, $author, $department, $course_strand, $document, $image]);

                // Activity log
                require_once __DIR__ . '/include/activity_log.php';
                log_activity($conn, 'admin', $_SESSION['admin_id'] ?? null, 'upload_research', [
                    'title' => $title,
                    'department' => $department,
                    'year' => $year,
                    'document' => $document,
                ]);
                $message = "Research project uploaded successfully!";
                $message_type = 'success';
                $_POST = []; // Clear form
            } catch (PDOException $e) {
                $message = "Database error: " . htmlspecialchars($e->getMessage());
                $message_type = 'error';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Research | SRC Research Repository</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        }
        .card-hover:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .upload-card {
            border: 2px dashed #3b82f6;
        }
        .upload-card:hover {
            border-color: #1d4ed8;
            background-color: #eff6ff;
        }
        .section-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 2px;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex">

    <!-- Include Sidebar -->
    <?php include 'admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8 space-y-8">
        <h1 class="text-3xl font-bold text-gray-800">Upload Research</h1>

        <!-- Message Output -->
        <?php if ($message): ?>
            <div id="alert-message" class="relative mb-6">
                <div class="flex items-center p-4 rounded-lg shadow-md text-white 
                    <?php echo $message_type === 'success' ? 'bg-green-500' : 'bg-red-500'; ?>">
                    <i class="fas fa-bell mr-3"></i>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
                <button onclick="document.getElementById('alert-message').remove()" 
                        class="absolute top-0 right-0 m-2 text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            <script>
                // Auto-hide message after 5 seconds
                setTimeout(function() {
                    const alert = document.getElementById('alert-message');
                    if (alert) {
                        alert.style.transition = "opacity 0.5s ease";
                        alert.style.opacity = "0";
                        setTimeout(() => alert.remove(), 500);
                    }
                }, 5000);
            </script>
        <?php endif; ?>

        <!-- Upload Research Form -->
        <section class="bg-white p-6 rounded-2xl shadow-lg card-hover transition-all duration-300 border border-gray-100 upload-card">
            <div class="flex items-center mb-6">
                <div class="bg-blue-100 p-2 rounded-xl mr-3">
                    <i class="fas fa-cloud-upload-alt text-blue-600 text-xl"></i>
                </div>
                <h3 class="section-header text-2xl font-bold text-gray-800 relative">Upload Research</h3>
            </div>

            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <!-- Title & Year -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Research Title *</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-500">
                                <i class="fas fa-book"></i>
                            </div>
                            <input type="text" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                                   class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Enter research title" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Academic/School Year *</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-500">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <select name="year" id="year_select" required class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Loading years...</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Abstract -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Abstract *</label>
                    <textarea name="abstract" rows="4"
                              class="w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="Enter a brief summary of your research..." required><?= htmlspecialchars($_POST['abstract'] ?? '') ?></textarea>
                </div>

                <!-- Keywords -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Keywords (comma-separated)</label>
                    <input type="text" name="keywords" value="<?= htmlspecialchars($_POST['keywords'] ?? '') ?>"
                           class="w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="e.g., machine learning, climate change, data mining">
                    <p class="text-xs text-gray-500 mt-1">Add 3–8 keywords separated by commas to improve search visibility.</p>
                </div>

                <!-- Members -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Author(s) *</label>
                    <textarea name="author" rows="2"
                              class="w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="Enter author names separated by commas" required><?= htmlspecialchars($_POST['author'] ?? '') ?></textarea>
                </div>

                <!-- Department, Course/Strand & Status -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Department *</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-500">
                                <i class="fas fa-building"></i>
                            </div>
                            <select name="department" id="department" required class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="" selected>Select Department</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2"><span id="course_label">Course/Strand</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-500">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <select name="course_strand" id="course_strand" class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select Course/Strand</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-green-500">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <input type="text" value="Approved" class="w-full pl-10 pr-3 py-3 border border-green-300 bg-green-50 text-green-800 rounded-xl" disabled>
                            <input type="hidden" name="status" value="1">
                        </div>
                    </div>
                </div>

                <!-- Image & Document Uploads -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Poster (Optional)</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-500">
                                <i class="fas fa-image"></i>
                            </div>
                            <input type="file" name="image" accept="image/*"
                                   class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-600 hover:file:bg-blue-100 transition-all duration-200">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Research Document (PDF) <span class=\"text-gray-400\">(Optional)</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-500">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <input type="file" name="document" accept=".pdf"
                                   class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-600 hover:file:bg-blue-100 transition-all duration-200">
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white py-4 rounded-xl transition-all duration-300 flex items-center justify-center font-medium text-lg shadow-lg hover:shadow-xl">
                    <i class="fas fa-upload mr-3 text-xl"></i>
                    Upload Research
                </button>
            </form>
        </section>
    </main>

    <script>
        // Dynamic Department and Course/Strand loading (DB-backed)
        (function(){
            const deptSel = document.getElementById('department');
            const csSel = document.getElementById('course_strand');
            const csLabel = document.getElementById('course_label');
            const yearSel = document.getElementById('year_select');
            const preselectedYear = <?= json_encode($_POST['year'] ?? '') ?>;
            const preselectedDept = <?= json_encode($_POST['department'] ?? '') ?>;
            const preselectedCourse = <?= json_encode($_POST['course_strand'] ?? '') ?>;

            let cachedYearSpans = [];

            function currentYearPrefixForDeptName(deptName) {
                if (!deptName) return 'A.Y.'; // default
                const dn = String(deptName).trim().toLowerCase();
                // Senior High School => S.Y.
                if (dn === 'senior high school' || dn === 'shs') return 'S.Y.';
                // CCS, COE, CBS and others => A.Y.
                return 'A.Y.';
            }

            function rebuildYearOptions(prefix) {
                yearSel.innerHTML = '';
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Select ' + (prefix === 'S.Y.' ? 'School' : 'Academic') + ' Year';
                placeholder.disabled = true;
                placeholder.selected = true;
                yearSel.appendChild(placeholder);

                cachedYearSpans.forEach(span => {
                    const full = `${prefix} ${span}`;
                    const opt = document.createElement('option');
                    opt.value = full;
                    opt.textContent = full;
                    if (preselectedYear && preselectedYear === full) opt.selected = true;
                    yearSel.appendChild(opt);
                });
                // If nothing preselected, default to the first available year
                if (!preselectedYear && yearSel.options.length > 1) {
                    yearSel.options[1].selected = true;
                }
            }

            async function loadAcademicYears(){
                try {
                    const res = await fetch('include/get_academic_years.php');
                    const json = await res.json();
                    if (!json.ok) throw new Error(json.error || 'Failed to load years');
                    cachedYearSpans = json.data.map(r => r.span);
                    const opt = deptSel.options[deptSel.selectedIndex];
                    const deptName = opt ? opt.value : '';
                    const prefix = currentYearPrefixForDeptName(deptName);
                    rebuildYearOptions(prefix);
                } catch(e) {
                    console.error(e);
                    yearSel.innerHTML = '<option value="">Failed to load years</option>';
                }
            }

            async function loadDepartments(){
                try {
                    const res = await fetch('include/get_departments.php');
                    const json = await res.json();
                    if (!json.ok) throw new Error(json.error || 'Failed to load departments');
                    const keep = deptSel.value;
                    deptSel.innerHTML = '<option value="">Select Department</option>';
                    json.data.forEach(d => {
                        const opt = document.createElement('option');
                        opt.value = d.name;
                        opt.textContent = d.name;
                        opt.dataset.deptId = d.id;
                        if ((keep && d.name === keep) || (preselectedDept && d.name === preselectedDept)) opt.selected = true;
                        deptSel.appendChild(opt);
                    });
                    // Ensure value is set if coming from server POST
                    if (preselectedDept) { deptSel.value = preselectedDept; }
                } catch(e) { console.error(e); }
            }

            async function loadStrands(){
                try {
                    const res = await fetch('include/get_strands.php');
                    const json = await res.json();
                    if (!json.ok) throw new Error(json.error || 'Failed to load strands');
                    csSel.innerHTML = '<option value="">Select Strand</option>';
                    json.data.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s.strand;
                        opt.textContent = s.strand;
                        csSel.appendChild(opt);
                    });
                    if (preselectedCourse) { csSel.value = preselectedCourse; }
                } catch(e) { console.error(e); }
            }

            async function loadCoursesForDepartmentId(deptId){
                try {
                    const res = await fetch('include/get_courses.php?department_id=' + encodeURIComponent(deptId));
                    const json = await res.json();
                    if (!json.ok) throw new Error(json.error || 'Failed to load courses');
                    csSel.innerHTML = '<option value="">Select Course</option>';
                    json.data.forEach(c => {
                        const opt = document.createElement('option');
                        opt.value = c.name;
                        opt.textContent = c.name + (c.code ? ` (${c.code})` : '');
                        csSel.appendChild(opt);
                    });
                    if (preselectedCourse) { csSel.value = preselectedCourse; }
                } catch(e) { console.error(e); }
            }

            async function onDepartmentChange(){
                const opt = deptSel.options[deptSel.selectedIndex];
                const deptId = opt ? opt.dataset.deptId : '';
                const deptName = opt ? opt.value : '';
                if (!deptId) {
                    csLabel.textContent = 'Course/Strand';
                    csSel.innerHTML = '<option value="">Select Course/Strand</option>';
                    // Also refresh year prefix to default when no department
                    rebuildYearOptions(currentYearPrefixForDeptName(''));
                    return;
                }
                if (deptName.toLowerCase() === 'senior high school') {
                    csLabel.textContent = 'Strand';
                    await loadStrands();
                } else {
                    csLabel.textContent = 'Course';
                    await loadCoursesForDepartmentId(deptId);
                }
                // Update year options prefix according to department
                rebuildYearOptions(currentYearPrefixForDeptName(deptName));
            }

            // Load all data
            Promise.all([loadDepartments(), loadAcademicYears()]).then(onDepartmentChange);
            deptSel.addEventListener('change', onDepartmentChange);
        })();
    </script>
</body>
</html>