<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db.php';

// Check if admin or a permitted sub-admin is logged in
$is_admin = isset($_SESSION['admin_id']);
$is_subadmin = isset($_SESSION['subadmin_id']);
$can_view = false;
$user_role = '';
$assigned_strand = '';
$permissions = [];

// Initialize debug and result variables to avoid undefined variable warnings
$debugInfo = '';
$queryInfo = '';
$errorInfo = '';
$pendingStudents = [];
$verifiedStudents = [];
$pendingCount = 0;
$verifiedCount = 0;
$fetched_from_db = false;
$fetched_strand = null;
$normalizedAssigned = '';

// Determine role and permissions
if ($is_admin) {
    $can_view = true;
    $user_role = 'admin';
    $permissions = ['all'];
} elseif ($is_subadmin) {
    // Allow any logged-in sub-admin to access their strand's student view.
    $permissions = json_decode($_SESSION['permissions'] ?? '[]', true);
    // Prefer department assignment
    $assigned_strand = $_SESSION['department'] ?? ($_SESSION['strand'] ?? '');
    // Fallback diagnostic variables
    $fetched_from_db = false;
    $fetched_strand = null;
    // If session strand is empty, try to fetch it from DB using subadmin_id
    if (isset($_SESSION['subadmin_id'])) {
        // Normalize $permissions to always be an array (avoid implode errors)
if (!is_array($permissions)) {
    $permRaw = $_SESSION['permissions'] ?? '';
    if (is_string($permRaw)) {
        $tryJson = json_decode($permRaw, true);
        if (is_array($tryJson)) {
            $permissions = $tryJson;
        } else {
            // Fallback: comma-separated string -> array
            $parts = array_filter(array_map('trim', explode(',', $permRaw)), function($x){ return $x !== '' && $x !== null; });
            $permissions = $parts ?: [];
        }
    } else {
        $permissions = [];
    }
}

try {
            // Read department (fallback legacy strand)
            $stmt = $conn->prepare("SELECT id, COALESCE(department, strand) AS strand FROM sub_admins WHERE id = ? LIMIT 1");
            $stmt->execute([$_SESSION['subadmin_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $fetched_strand = $row['strand'] ?? null;
                if (!empty($fetched_strand)) {
                    $assigned_strand = $fetched_strand;
                    $_SESSION['department'] = $assigned_strand;
                    $fetched_from_db = true;
                }
            }
        } catch (PDOException $e) {
            error_log('Failed to fetch sub-admin strand in view_students: ' . $e->getMessage());
        }
    }
    $can_view = true;
    $user_role = 'subadmin';
}

if (!$can_view) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    if ($is_subadmin) {
        header("Location: subadmin_dashboard.php");
    } else {
        header("Location: login.php");
    }
    exit();
}

try {
    // Detect which name columns exist in students
    $cols = [];
    try {
        $qCols = $conn->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students'");
        $qCols->execute();
        $cols = $qCols->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (Throwable $_) { $cols = []; }
    $firstCol = in_array('firstname', $cols, true) ? 'firstname' : (in_array('first_name', $cols, true) ? 'first_name' : null);
    $lastCol  = in_array('lastname',  $cols, true) ? 'lastname'  : (in_array('last_name',  $cols, true) ? 'last_name'  : null);
    $orderByName = 'email';
    if ($lastCol && $firstCol) {
        $orderByName = "`$lastCol`, `$firstCol`";
    } elseif ($lastCol) {
        $orderByName = "`$lastCol`";
    } elseif ($firstCol) {
        $orderByName = "`$firstCol`";
    }

    // Prepare and execute pending and verified queries based on actual account type
    if ($is_admin) {
        // Admin: global view, no grade restriction
        $pendingStmt = $conn->prepare("SELECT * FROM students WHERE is_verified = 0 ORDER BY $orderByName");
        $verifiedStmt = $conn->prepare("SELECT * FROM students WHERE is_verified = 1 ORDER BY $orderByName");
        $pendingStmt->execute();
        $verifiedStmt->execute();

        $pendingStudents = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
        $verifiedStudents = $verifiedStmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($is_subadmin) {
        // Use the same logic as subadmin_dashboard.php for consistency
        if (!empty($assigned_strand)) {
            $deptKey = strtolower(trim($assigned_strand));
            $whereDept = 'TRIM(LOWER(COALESCE(department,\'\'))) = TRIM(LOWER(?))';
            $params = [$deptKey];
            
            // Get pending students
            $sqlPending = "SELECT * FROM students WHERE is_verified = 0 AND $whereDept ORDER BY $orderByName";
            $pendingStmt = $conn->prepare($sqlPending);
            $pendingStmt->execute($params);
            $pendingStudents = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get verified students
            $sqlVerified = "SELECT * FROM students WHERE is_verified = 1 AND $whereDept ORDER BY $orderByName";
            $verifiedStmt = $conn->prepare($sqlVerified);
            $verifiedStmt->execute($params);
            $verifiedStudents = $verifiedStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Update verified count
            $verifiedCount = count($verifiedStudents);
            
            // Debug logging
            error_log("Found " . count($pendingStudents) . " pending students");
            error_log("Found " . count($verifiedStudents) . " verified students");
            
            // Log each verified student for debugging
            foreach ($verifiedStudents as $student) {
                $fname = $firstCol ? ($student[$firstCol] ?? '') : '';
                $lname = $lastCol ? ($student[$lastCol] ?? '') : '';
                error_log("Verified student found: " . $fname . " " . $lname . 
                         " - Department: " . ($student['department'] ?? $student['strand']) . 
                         " - Status: " . $student['is_verified']);
            }
        } else {
            // If no assigned strand is set, fall back to showing nothing for safety
            $pendingStudents = [];
            $verifiedStudents = [];
        }
    } else {
        // Not admin nor subadmin: empty results
        $pendingStudents = [];
        $verifiedStudents = [];
    }

    $pendingCount = count($pendingStudents);
    $verifiedCount = count($verifiedStudents);

    // We no longer need PHP-side filtering since we're filtering in the SQL query
    if ($is_subadmin && empty($assigned_strand)) {
        // If no strand is assigned, show no students for safety
        $pendingStudents = [];
        $verifiedStudents = [];

        // Recalculate counts after filtering
        $pendingCount = count($pendingStudents);
        $verifiedCount = count($verifiedStudents);
    }

    // Build distinct filter options for verified students (only department retained)
    $strandOptions = [];
    foreach ($verifiedStudents as $s) {
        if (!empty($s['department'])) $strandOptions[strtolower($s['department'])] = $s['department'];
        elseif (!empty($s['strand'])) $strandOptions[strtolower($s['strand'])] = $s['strand'];
    }

    $debugInfo .= "<div class='bg-gray-100 p-4 mb-4 rounded'>";
    $debugInfo .= "<h3 class='font-bold mb-2'>Debug Information:</h3>";
    $debugInfo .= "<p><strong>User Role:</strong> " . htmlspecialchars($user_role) . "</p>";
    $debugInfo .= "<p><strong>Session ID:</strong> " . session_id() . "</p>";
    $debugInfo .= "<p><strong>Subadmin ID:</strong> " . htmlspecialchars($_SESSION['subadmin_id'] ?? 'not set') . "</p>";
    $debugInfo .= "<p><strong>Assigned Department:</strong> " . htmlspecialchars($assigned_strand) . "</p>";
    $debugInfo .= "<p><strong>Session Department:</strong> " . htmlspecialchars($_SESSION['department'] ?? 'not set') . "</p>";
    $debugInfo .= "<p><strong>Fetched DB Department:</strong> " . htmlspecialchars($fetched_strand ?? 'not set') . "</p>";
    $debugInfo .= "<p><strong>Permissions:</strong> " . htmlspecialchars(implode(", ", (array)$permissions)) . "</p>";
    $debugInfo .= "</div>";

    $queryInfo .= "<div class='bg-blue-100 p-4 mb-4 rounded'>";
    $queryInfo .= "<h3 class='font-bold mb-2'>Query Information:</h3>";
    $queryInfo .= "<p><strong>Pending students:</strong> " . $pendingCount . "</p>";
    $queryInfo .= "<p><strong>Verified students:</strong> " . $verifiedCount . "</p>";
    $queryInfo .= "</div>";

    // Build a small diagnostic listing (not shown by default). Enable by adding ?debug=1 to the URL.
    $diagnosticHtml = "<div class='bg-yellow-50 p-4 mb-4 rounded'>";
    $diagnosticHtml .= "<h3 class='font-bold mb-2'>Diagnostics (enable with ?debug=1)</h3>";
    $diagnosticHtml .= "<p><strong>Normalized Assigned Strand:</strong> " . htmlspecialchars($normalizedAssigned ?? '(not set)') . "</p>";
    $diagnosticHtml .= "<p><strong>Session subadmin_id:</strong> " . htmlspecialchars($_SESSION['subadmin_id'] ?? '(not set)') . "</p>";
    $diagnosticHtml .= "<p><strong>Fetched from DB:</strong> " . ($fetched_from_db ? 'yes' : 'no') . "</p>";
    $diagnosticHtml .= "<p><strong>Fetched value:</strong> " . htmlspecialchars($fetched_strand ?? '(none)') . "</p>";
    $diagnosticHtml .= "<p><strong>Verified fetched (count):</strong> " . count($verifiedStudents) . "</p>";
    $diagnosticHtml .= "<div class='overflow-x-auto'><table class='min-w-full bg-white border mt-2'><thead class='bg-gray-100'><tr><th class='px-4 py-2'>Student Number</th><th class='px-4 py-2'>Department</th><th class='px-4 py-2'>Normalized</th><th class='px-4 py-2'>Verified</th></tr></thead><tbody>";
    $sample = array_slice($verifiedStudents, 0, 30);
    foreach ($sample as $srow) {
        $sr_lrn = htmlspecialchars($srow['student_id'] ?? '');
        $sr_strand = htmlspecialchars($srow['department'] ?? ($srow['strand'] ?? ''));
        $sr_norm = htmlspecialchars(preg_replace('/[^a-z0-9]/', '', strtolower($srow['department'] ?? ($srow['strand'] ?? ''))));
        $sr_ver = htmlspecialchars($srow['is_verified'] ?? '');
        $diagnosticHtml .= "<tr class='border-t'><td class='px-4 py-2'>" . $sr_lrn . "</td><td class='px-4 py-2'>" . $sr_strand . "</td><td class='px-4 py-2'>" . $sr_norm . "</td><td class='px-4 py-2'>" . $sr_ver . "</td></tr>";
    }
    $diagnosticHtml .= "</tbody></table></div></div>";

} catch (PDOException $e) {
    $errorInfo .= "<div class='bg-red-100 p-4 mb-4 rounded'>";
    $errorInfo .= "<p><strong>Database Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    $errorInfo .= "</div>";
    error_log("Database error in student view: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Students</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 flex">

    <!-- Sidebar -->
    <?php include 'subadmin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-10">
        <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <?php // Debug info removed for cleaner UI. Enable during troubleshooting by adding ?debug=1 to the URL ?>
            <?php if (!empty($_GET['debug']) && $_GET['debug'] == '1'): ?>
                <?php echo $errorInfo ?? ''; ?>
                <?php echo $debugInfo ?? ''; ?>
                <?php echo $queryInfo ?? ''; ?>
                <?php echo $diagnosticHtml ?? ''; ?>
            <?php endif; ?>

            <h2 class="text-4xl font-extrabold text-blue-900 mb-6">
                <?php if ($user_role === 'admin'): ?>
                    Students
                <?php else: ?>
                    Students • Department: <?php echo htmlspecialchars($assigned_strand); ?>
                <?php endif; ?>
            </h2>
            <?php if ($user_role === 'subadmin'): ?>
                <p class="text-gray-600 mb-4">Showing students from your assigned department (<?php echo htmlspecialchars($assigned_strand); ?>)</p>
            <?php endif; ?>
            
            <!-- Pending Verification removed; pending students handled elsewhere -->

            <!-- Verified Students Table -->
            <h3 class="text-2xl font-bold text-gray-800 mb-4">Verified Students (<?php echo $verifiedCount ?? 0; ?>)</h3>
            <!-- Filters -->
            <div class="w-full grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-2 xl:gap-3 mb-4">
                <input id="vsSearch" type="text" placeholder="Search name, email, Student Number" class="border rounded px-3 py-2 text-sm w-full" />
                <div></div>
                <div></div>
            </div>

            <div class="overflow-x-auto hidden xl:block">
                <table class="min-w-full bg-white border border-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 border-b text-left text-xs font-semibold text-gray-600 uppercase">Profile</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-semibold text-gray-600 uppercase">Student Number</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-semibold text-gray-600 uppercase">First Name</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-semibold text-gray-600 uppercase">Last Name</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-semibold text-gray-600 uppercase">Email</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-semibold text-gray-600 uppercase">Department</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (!empty($verifiedStudents)): ?>
                            <?php foreach ($verifiedStudents as $row): ?>
                                <?php $fname = $firstCol ? ($row[$firstCol] ?? '') : ''; $lname = $lastCol ? ($row[$lastCol] ?? '') : ''; ?>
                                <tr class="hover:bg-gray-50" data-name="<?= htmlspecialchars(strtolower($fname.' '.$lname)) ?>" data-email="<?= htmlspecialchars(strtolower($row['email'] ?? '')) ?>" data-studnum="<?= htmlspecialchars(strtolower($row['student_id'] ?? '')) ?>" data-department="<?= htmlspecialchars(strtolower($row['department'] ?? ($row['strand'] ?? ''))) ?>">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php $pic = !empty($row['profile_pic']) ? 'images/' . htmlspecialchars($row['profile_pic']) : 'images/default.jpg'; ?>
                                        <img src="<?= $pic ?>" alt="Profile" class="w-10 h-10 rounded-full object-cover border" />
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($row['student_id'] ?? 'N/A'); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($fname); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($lname); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($row['department'] ?? ($row['strand'] ?? 'Not Set')); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        <?php if ((int)($row['is_verified'] ?? 0) === 1): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Approved</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="9" class="px-6 py-4 text-center text-gray-500">No verified students.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile / Tablet Card List -->
            <div class="xl:hidden grid grid-cols-1 gap-3 mt-4">
                <?php if (!empty($verifiedStudents)): ?>
                    <?php foreach ($verifiedStudents as $row): ?>
                        <?php $pic = !empty($row['profile_pic']) ? 'images/' . htmlspecialchars($row['profile_pic']) : 'images/default.jpg'; ?>
                        <?php $fname = $firstCol ? ($row[$firstCol] ?? '') : ''; $lname = $lastCol ? ($row[$lastCol] ?? '') : ''; ?>
                        <div class="bg-white rounded-lg shadow p-4" data-name="<?= htmlspecialchars(strtolower($fname.' '.$lname)) ?>" data-email="<?= htmlspecialchars(strtolower($row['email'] ?? '')) ?>" data-studnum="<?= htmlspecialchars(strtolower($row['student_id'] ?? '')) ?>" data-department="<?= htmlspecialchars(strtolower($row['department'] ?? ($row['strand'] ?? ''))) ?>">
                            <div class="flex items-start gap-3">
                                <img src="<?= $pic ?>" alt="Profile" class="w-12 h-12 rounded-full object-cover border">
                                <div class="min-w-0">
                                    <h4 class="text-base font-semibold text-gray-900 truncate"><?= htmlspecialchars($fname.' '.$lname) ?></h4>
                                    <p class="text-xs text-gray-500">Student Number: <span class="font-medium text-gray-700"><?= htmlspecialchars($row['student_id'] ?? 'N/A') ?></span></p>
                                    <p class="text-xs text-gray-500">Email: <span class="font-medium text-gray-700"><?= htmlspecialchars($row['email'] ?? 'N/A') ?></span></p>
                                    <p class="text-xs text-gray-500">Department: <span class="font-medium text-gray-700"><?= htmlspecialchars($row['department'] ?? ($row['strand'] ?? 'Not Set')) ?></span></p>
                                </div>
                            </div>
                            <div class="mt-3 flex items-center justify-between">
                                <?php if ((int)($row['is_verified'] ?? 0) === 1): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Approved</span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                <?php endif; ?>
                                <button type="button" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-sm" onclick="showProfileModal('<?= htmlspecialchars(addslashes($fname)) ?>','<?= htmlspecialchars(addslashes($lname)) ?>','<?= htmlspecialchars(addslashes($row['email']??'')) ?>','<?= htmlspecialchars(addslashes($row['student_id']??'')) ?>','<?= htmlspecialchars(addslashes($row['grade'] ?? '')) ?>','<?= htmlspecialchars(addslashes($row['department'] ?? ($row['strand']??''))) ?>','','','<?= htmlspecialchars(addslashes($row['profile_pic']??'')) ?>')">
                                    <i class="fas fa-user mr-1"></i> View
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-gray-600 p-6">
                        <div class="flex flex-col items-center">
                            <i class="fas fa-users text-3xl mb-2 opacity-50"></i>
                            <span>No verified students.</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Profile Modal -->
            <div id="profileModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden transition-opacity duration-300">
                <div class="bg-white rounded-lg shadow-xl p-8 max-w-sm w-full relative transform transition-all duration-300 scale-95">
                    <button onclick="closeProfileModal()" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                    <div class="flex flex-col items-center text-center">
                        <img id="modalProfilePic" src="" alt="Profile Picture" class="w-32 h-32 rounded-full border-4 border-blue-500 mb-4 object-cover shadow-lg">
                        <h3 id="modalName" class="text-2xl font-bold text-gray-800 mb-2"></h3>
                        <div class="text-sm text-gray-700 space-y-1">
                            <p id="modalEmail"></p>
                            <p id="modalLRN"></p>
                            <p id="modalGrade"></p>
                            <p id="modalStrand"></p>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                const profileModal = document.getElementById('profileModal');
                const modalContent = profileModal.querySelector('.transform');

                function showProfileModal(firstname, lastname, email, studnum, grade, department, _section, _groupNumber, profilePic) {
                    document.getElementById('modalName').textContent = firstname + ' ' + lastname;
                    document.getElementById('modalEmail').textContent = 'Email: ' + (email || '');
                    document.getElementById('modalLRN').textContent = 'Student Number: ' + (studnum || '');
                    document.getElementById('modalGrade').textContent = 'Grade: ' + (grade || '');
                    document.getElementById('modalStrand').textContent = 'Department: ' + (department || '');
                    document.getElementById('modalProfilePic').src = profilePic ? 'images/' + profilePic : 'images/default.jpg';
                    
                    profileModal.classList.remove('hidden');
                    setTimeout(() => {
                        profileModal.style.opacity = '1';
                        modalContent.style.transform = 'scale(1)';
                    }, 10);
                }

                function closeProfileModal() {
                    profileModal.style.opacity = '0';
                    modalContent.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        profileModal.classList.add('hidden');
                    }, 300);
                }

                // Close modal on outside click
                profileModal.addEventListener('click', function(e) {
                    if (e.target === profileModal) {
                        closeProfileModal();
                    }
                });

                // Client-side filtering for table rows and cards
                (function(){
                    const q = document.getElementById('vsSearch');
                    function norm(x){ return (x||'').toString().trim().toLowerCase(); }
                    function matches(el){
                        const name = el.getAttribute('data-name')||'';
                        const email = el.getAttribute('data-email')||'';
                        const studnum = el.getAttribute('data-studnum')||'';
                        const department = el.getAttribute('data-department')||'';
                        const qq = norm(q.value);
                        if (qq && !(name.includes(qq) || email.includes(qq) || studnum.includes(qq))) return false;
                        return true;
                    }
                    function apply(){
                        document.querySelectorAll('tbody tr[data-name]').forEach(tr=>{
                            tr.style.display = matches(tr) ? '' : 'none';
                        });
                        document.querySelectorAll('.xl\\:hidden [data-name]').forEach(card=>{
                            const show = matches(card);
                            card.style.display = show ? '' : 'none';
                        });
                    }
                    ['input','change'].forEach(ev=>{
                        q.addEventListener(ev, apply);
                    });
                    apply();
                })();
            </script>
            
            
        </div>
    </main>
   
</body>
</html>
