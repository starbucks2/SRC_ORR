<?php
session_start();
require_once 'db.php';
// Load Composer autoload if available (for PhpSpreadsheet)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Permission gate: allow if admin OR session permissions include import_students or import_students_<strand>
$is_admin = isset($_SESSION['admin_id']);
$strand = isset($_SESSION['strand']) ? strtolower((string)$_SESSION['strand']) : '';
$permissions = [];
if (isset($_SESSION['permissions'])) {
    $permissions = is_array($_SESSION['permissions']) ? $_SESSION['permissions'] : (json_decode($_SESSION['permissions'], true) ?: []);
}
$can_import = $is_admin || in_array('import_students', $permissions, true) || ($strand && in_array('import_students_' . $strand, $permissions, true));
if (!$can_import) {
    $_SESSION['error'] = 'You do not have permission to import students.';
    header('Location: admin_dashboard.php');
    exit();
}

$errors = [];
$results = [
    'inserted' => 0,
    'updated' => 0,
    'skipped' => 0,
    'rows' => [] // each: ['line' => n, 'status' => 'inserted|updated|skipped', 'message' => '...']
];

// Common validation maps (aligned with new schema)
$valid_departments = ['CCS','CBS','COE','Senior High School'];

// Ensure students table has required columns similar to register.php
try {
    // groups table
    $conn->exec("CREATE TABLE IF NOT EXISTS `groups` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `group_number` INT NOT NULL UNIQUE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // seed 1..10 groups
    $insGrp = $conn->prepare("INSERT IGNORE INTO `groups` (group_number) VALUES (?), (?), (?), (?), (?), (?), (?), (?), (?), (?)");
    $insGrp->execute([1,2,3,4,5,6,7,8,9,10]);
    // student_groups
    $conn->exec("CREATE TABLE IF NOT EXISTS student_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        group_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student (student_id),
        INDEX idx_group (group_id),
        CONSTRAINT fk_sg_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
        CONSTRAINT fk_sg_group FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Ensure modern columns exist
    $colCheckDept = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'department'");
    $colCheckDept->execute();
    if ((int)$colCheckDept->fetchColumn() === 0) {
        $conn->exec("ALTER TABLE students ADD COLUMN department VARCHAR(50) NULL AFTER email");
    }
    $colCheckSID = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'student_id'");
    $colCheckSID->execute();
    if ((int)$colCheckSID->fetchColumn() === 0) {
        $conn->exec("ALTER TABLE students ADD COLUMN student_id VARCHAR(32) NULL AFTER department, ADD UNIQUE KEY uniq_student_id (student_id)");
    }
    $colCheckPwd = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'password'");
    $colCheckPwd->execute();
    if ((int)$colCheckPwd->fetchColumn() === 0) {
        $conn->exec("ALTER TABLE students ADD COLUMN password VARCHAR(255) NOT NULL AFTER student_id");
    }
    // ensure is_verified column exists
    $colCheckVerified = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'is_verified'");
    $colCheckVerified->execute();
    if ((int)$colCheckVerified->fetchColumn() === 0) {
        $conn->exec("ALTER TABLE students ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER password");
    }
} catch (PDOException $e) {
    $errors[] = 'Failed to prepare database for import: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if (!empty($_FILES['csv_file']['error'])) {
        $errors[] = 'File upload error.';
    } else {
        $tmp = $_FILES['csv_file']['tmp_name'];
        $name = $_FILES['csv_file']['name'];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        // Helper to process array of rows (first row headers)
        $processRows = function(array $allRows) use (&$results, &$errors, $valid_departments, $conn) {
            $line = 0; $headers = []; $idx = [];
            foreach ($allRows as $row) {
                $line++;
                if (!is_array($row)) { $row = [$row]; }
                // Trim all values
                $row = array_map(function($v){ return is_string($v) ? trim($v) : (string)$v; }, $row);
                $nonEmptyCount = count(array_filter($row, function($v){ return $v !== null && $v !== ''; }));
                if ($row === [null] || $nonEmptyCount === 0) { continue; }
                if ($line === 1) {
                    // Normalize headers: trim, lowercase, remove spaces
                    $headers = array_map(function($h) {
                        return strtolower(str_replace(' ', '', trim($h)));
                    }, $row);
                    // Accept legacy header 'lrn' or new 'student_id'; and 'strand' or 'department'
                    $expected = ['lrn','student_id','studentid','lastname','firstname','middlename','suffix','strand','department'];
                    if (count($headers) === 0) { $errors[] = 'No headers found.'; break; }
                    // Build index but do not require all expected; we'll map with fallbacks
                    $idx = array_flip($headers);
                    continue;
                }
                $data = [];
                foreach ($headers as $i => $h) { 
                    $data[$h] = isset($row[$i]) ? trim((string)$row[$i]) : ''; 
                }
                // Map legacy to new keys with proper null coalescing
                $studentId = '';
                if (isset($data['student_id']) && $data['student_id'] !== '') {
                    $studentId = $data['student_id'];
                } elseif (isset($data['studentid']) && $data['studentid'] !== '') {
                    $studentId = $data['studentid'];
                } elseif (isset($data['lrn'])) {
                    $studentId = $data['lrn'];
                }
                $studentId = trim($studentId);
                
                $department = '';
                if (isset($data['department']) && $data['department'] !== '') {
                    $department = $data['department'];
                } elseif (isset($data['strand'])) {
                    $department = $data['strand'];
                }
                $department = trim($department);

                $msg = ''; $status = 'skipped';
                do {
                    // Required minimal fields (student_id, firstname, lastname, department)
                    if ($studentId === '' || ($data['lastname'] ?? '') === '' || ($data['firstname'] ?? '') === '' || $department === '') { $msg = 'Required fields missing (Student ID, Lastname, Firstname, Department).'; break; }
                    // Validate Student ID format: YY-XXXXXXX
                    if (!preg_match('/^\d{2}-\d{7}$/', $studentId)) { $msg = 'Invalid Student ID (use format: YY-XXXXXXX, e.g., 22-0002155).'; break; }
                    // Validate department (map legacy strands to departments if you have fixed set)
                    if (!in_array($department, $valid_departments, true)) { /* allow any non-empty department for flexibility */ }

                    // Email should be based on first name; password should be the Student ID
                    $baseLocal = strtolower(preg_replace('/[^a-z0-9]+/i', '', $data['firstname']));
                    if ($baseLocal === '') { $baseLocal = 'student'; }
                    // ensure email uniqueness by appending a numeric suffix if needed
                    $emailCandidate = $baseLocal . '@src.edu.ph';
                    $suffixN = 1;
                    $email = $emailCandidate;
                    $checkEmail = $conn->prepare('SELECT COUNT(*) FROM students WHERE LOWER(email) = LOWER(?)');
                    while (true) {
                        $checkEmail->execute([$email]);
                        $existsEmail = (int)$checkEmail->fetchColumn();
                        if ($existsEmail === 0) { break; }
                        $email = $baseLocal . $suffixN . '@src.edu.ph';
                        $suffixN++;
                    }
                    $plainPass = $studentId;
                    $hashed = password_hash($plainPass, PASSWORD_DEFAULT);

                    // Check by Student ID first (do NOT cast to int; student_id contains dash)
                    $findBySID = $conn->prepare('SELECT student_id FROM students WHERE student_id = ?');
                    $findBySID->execute([$studentId]);
                    $existsId = $findBySID->fetchColumn();

                    $suffix = isset($data['suffix']) ? $data['suffix'] : '';
                    
                    if ($existsId !== false) {
                        $stmt = $conn->prepare('UPDATE students SET firstname=?, middlename=?, lastname=?, suffix=?, email=?, department=?, password=?, is_verified = 1 WHERE student_id = ?');
                        $stmt->execute([$data['firstname'], $data['middlename'], $data['lastname'], $suffix, $email, $department, $hashed, $studentId]);
                        $status = 'updated'; $msg = 'Updated existing student.';
                    } else {
                        $stmt = $conn->prepare('INSERT INTO students (firstname, middlename, lastname, suffix, email, department, student_id, password, profile_pic, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
                        $profilePicName = 'default.jpg';
                        $stmt->execute([$data['firstname'], $data['middlename'], $data['lastname'], $suffix, $email, $department, $studentId, $hashed, $profilePicName]);
                        $newId = (int)$conn->lastInsertId();
                        $status = 'inserted'; $msg = 'Inserted new student. Email: ' . $email . ' | Password: (Student ID) ' . $plainPass;
                    }
                } while(false);

                if ($status === 'inserted') $results['inserted']++;
                elseif ($status === 'updated') $results['updated']++;
                else $results['skipped']++;
                $results['rows'][] = ['line' => $line, 'status' => $status, 'message' => $msg];
            }
        };

        if ($ext === 'csv') {
            $handle = fopen($tmp, 'r');
            if ($handle === false) { $errors[] = 'Unable to read the uploaded CSV file.'; }
            else {
                $rows = [];
                while (($row = fgetcsv($handle)) !== false) { $rows[] = $row; }
                fclose($handle);
                $processRows($rows);
            }
        } elseif ($ext === 'xlsx') {
            if (class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
                try {
                    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
                    $spreadsheet = $reader->load($tmp);
                    $sheet = $spreadsheet->getActiveSheet();
                    $rows = [];
                    foreach ($sheet->getRowIterator() as $row) {
                        $cellIterator = $row->getCellIterator();
                        $cellIterator->setIterateOnlyExistingCells(false);
                        $r = [];
                        foreach ($cellIterator as $cell) { $r[] = trim((string)$cell->getValue()); }
                        $rows[] = $r;
                    }
                    $processRows($rows);
                } catch (\Throwable $e) {
                    $errors[] = 'Failed to read XLSX: ' . $e->getMessage();
                }
            } else {
                $errors[] = 'XLSX not supported. Please install phpoffice/phpspreadsheet via Composer or upload a CSV file.';
            }
        } else {
            $errors[] = 'Unsupported file format. Please upload a CSV or XLSX file.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Import Students</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen flex flex-col md:flex-row">
    <?php include 'admin_sidebar.php'; ?>
    <main class="flex-1 p-4 sm:p-6 md:p-8 w-full">
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-200 max-w-4xl">
            <h1 class="text-2xl font-bold text-blue-900 flex items-center gap-2 mb-4">
                <i class="fas fa-file-import"></i>
                Import Students (Excel or CSV)
            </h1>

            <p class="text-sm text-gray-600 mb-4">
                Upload a CSV or Excel (.xlsx) file with the following headers:
                <code>student_id, lastname, firstname, middlename, suffix, department</code>.
                The Student ID must use the format <strong>YY-XXXXXXX</strong> (e.g., <em>22-0002155</em>).
                <strong>All imported students are auto-verified.</strong>
                Their email will be set to <em>firstname</em>@src.edu.ph (e.g., <em>maria@src.edu.ph</em>), and their password will be their <strong>Student ID</strong> (e.g., <em>22-0002155</em>).
                For compatibility, the importer will also accept legacy headers <code>lrn</code> (mapped to <code>student_id</code>) and <code>strand</code> (mapped to <code>department</code>).
            </p>
            <?php if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')): ?>
                <div class="mb-4 p-3 bg-amber-50 text-amber-800 border border-amber-200 rounded">
                    Excel (.xlsx) parsing is currently unavailable. To enable it, install <code>phpoffice/phpspreadsheet</code> via Composer, or upload a CSV instead.
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="mb-4 p-3 bg-red-100 text-red-700 border border-red-400 rounded">
                    <ul class="list-disc ml-5">
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="mb-4">
                <a href="admin_download_student_template.php" class="inline-flex items-center gap-2 px-3 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                    <i class="fas fa-download"></i> Download CSV Template
                </a>
                <a href="admin_download_student_template_xlsx.php" class="inline-flex items-center gap-2 px-3 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700 ml-2">
                    <i class="fas fa-file-excel"></i> Download Excel Template
                </a>
            </div>

            <form action="admin_import_students.php" method="post" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Excel/CSV File</label>
                    <input type="file" name="csv_file" accept=".csv,.xlsx" class="block w-full border rounded p-2" required>
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    <i class="fas fa-upload mr-2"></i>Upload & Import
                </button>
            </form>

            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)): ?>
                <div class="mt-6">
                    <h2 class="text-xl font-semibold mb-2">Import Summary</h2>
                    <p class="text-sm text-gray-700 mb-3">Inserted: <strong><?= (int)$results['inserted'] ?></strong> · Updated: <strong><?= (int)$results['updated'] ?></strong> · Skipped: <strong><?= (int)$results['skipped'] ?></strong></p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full border text-sm">
                            <thead>
                                <tr class="bg-gray-100 text-left">
                                    <th class="border p-2">Line</th>
                                    <th class="border p-2">Status</th>
                                    <th class="border p-2">Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results['rows'] as $r): ?>
                                <tr>
                                    <td class="border p-2"><?= (int)$r['line'] ?></td>
                                    <td class="border p-2 capitalize">
                                        <?php if ($r['status'] === 'inserted'): ?>
                                            <span class="px-2 py-1 rounded bg-green-100 text-green-800">inserted</span>
                                        <?php elseif ($r['status'] === 'updated'): ?>
                                            <span class="px-2 py-1 rounded bg-blue-100 text-blue-800">updated</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 rounded bg-yellow-100 text-yellow-800">skipped</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="border p-2"><?= htmlspecialchars($r['message']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
