<?php
session_start();
include 'db.php';

// Determine department based on session (students and sub-admins restricted) or URL
$user_department = null;
if (isset($_SESSION['user_type']) && !empty($_SESSION['department']) &&
    (($_SESSION['user_type'] === 'student') || ($_SESSION['user_type'] === 'sub_admins'))) {
    $user_department = $_SESSION['department'];
    $department_filter = $user_department; // force to user's department
} else {
    // Others can use URL filter (accept legacy strand for compatibility)
    $department_filter = $_GET['department'] ?? ($_GET['strand'] ?? 'all');
}

$research_papers = [];
// If a student is logged in, allow them to see their own submissions regardless of status
$current_student_id = (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'student' && isset($_SESSION['student_id']))
    ? $_SESSION['student_id']
    : null;

// Ensure these exist even if an exception path skips assignments later
$filtered_total_items = 0;
$filtered_total_pages = 0;

// --- Helper functions for citations ---
if (!function_exists('format_author_name_apa')) {
    function format_author_name_apa($name) {
        $name = trim($name);
        if ($name === '') return '';
        // Try to split "Lastname, Firstname Middlename" or "Firstname Middlename Lastname"
        if (strpos($name, ',') !== false) {
            // Already "Last, First Middle"
            [$last, $firsts] = array_map('trim', array_pad(explode(',', $name, 2), 2, ''));
        } else {
            $parts = preg_split('/\s+/', $name);
            $last = array_pop($parts);
            $firsts = implode(' ', $parts);
        }
        $initials = '';
        foreach (preg_split('/\s+/', $firsts) as $p) {
            $p = trim($p);
            if ($p !== '') $initials .= strtoupper(mb_substr($p, 0, 1)) . '. ';
        }
        $initials = trim($initials);
        if ($last === '') return $initials;
        return $last . ($initials ? ', ' . $initials : '');
    }
}

if (!function_exists('format_authors_apa')) {
    function format_authors_apa($members_raw) {
        if (!$members_raw) return '';
        // Split by commas or ' and '
        $authors = preg_split('/\s*,\s*|\s+and\s+/i', $members_raw);
        // Use a traditional anonymous function for broader PHP compatibility
        $authors = array_values(
            array_filter(
                array_map('trim', $authors),
                function ($a) { return $a !== ''; }
            )
        );
        $formatted = array_map('format_author_name_apa', $authors);
        $count = count($formatted);
        if ($count === 0) return '';
        if ($count == 1) return $formatted[0];
        if ($count <= 20) {
            $last = array_pop($formatted);
            return implode(', ', $formatted) . ', & ' . $last;
        }
        // More than 20: list first 19, an ellipsis, then last
        $first19 = array_slice($formatted, 0, 19);
        $last = end($formatted);
        return implode(', ', $first19) . ', ... ' . $last;
    }
}

if (!function_exists('build_absolute_url')) {
    function build_absolute_url($relativePath) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $rel = ltrim($relativePath, '/');
        return $scheme . '://' . $host . $base . '/' . $rel;
    }
}

// Academic year filtering (required). Compute selected AY and use it throughout
$__academic_year = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : (isset($_GET['school_year']) ? trim($_GET['school_year']) : '');
// Default to current AY if none provided (June cutoff)
if ($__academic_year === '') {
    $nowYear = (int)date('Y');
    $nowMonth = (int)date('n');
    $startYear = ($nowMonth >= 6) ? $nowYear : ($nowYear - 1);
    $__academic_year = $startYear . '-' . ($startYear + 1);
}
// We will filter by substring match on the 'YYYY-YYYY' portion
$__ay_like = '%' . $__academic_year . '%';

try {
    // First get total count for pagination with proper department handling
    $count_query = "SELECT COUNT(DISTINCT rs.document) as total 
                    FROM research_submission rs 
                    LEFT JOIN students s ON rs.student_id = s.student_id 
                    WHERE (rs.status = 1" . ($current_student_id ? " OR rs.student_id = ?" : "") . ")
                      AND rs.year LIKE ?";
    $count_params = [];
    if ($current_student_id) { $count_params[] = $current_student_id; }
    $count_params[] = $__ay_like;
    // Enforce student's department if logged in as student; otherwise honor URL filter
    if ($user_department !== null) {
        $count_query .= " AND (rs.department = ? OR s.department = ?)";
        $count_params[] = $user_department;
        $count_params[] = $user_department;
    } elseif ($department_filter !== 'all') {
        $count_query .= " AND (rs.department = ? OR s.department = ?)";
        $count_params[] = $department_filter;
        $count_params[] = $department_filter;
    }
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_items = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calculate pagination values
    $items_per_page = 10;
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $current_page = max(1, $current_page); // Ensure page is at least 1
    $total_pages = ceil($total_items / $items_per_page);
    $current_page = min($current_page, max(1, $total_pages)); // Ensure page doesn't exceed total
    $offset = ($current_page - 1) * $items_per_page;

    // Main query with direct department handling from research_submission
    $query = "SELECT 
                rs.id,
                rs.title,
                rs.year,
                rs.abstract,
                rs.keywords,
                rs.members,
                rs.department,
                rs.image,
                rs.document,
                rs.views,
                rs.submission_date,
                rs.student_id,
                s.firstname AS student_firstname,
                CASE 
                    WHEN rs.student_id > 0 THEN 'student'
                    ELSE 'admin'
                END as uploader_type
            FROM research_submission rs
            LEFT JOIN students s ON rs.student_id = s.student_id
            WHERE (rs.status = 1" . ($current_student_id ? " OR rs.student_id = ?" : "") . ")
              AND rs.document IS NOT NULL AND rs.document <> ''
              AND rs.year LIKE ?";

    $params = [];
    if ($current_student_id) { $params[] = $current_student_id; }
    $params[] = $__ay_like;
    // Enforce student's department if logged in as student; otherwise honor URL filter
    if ($user_department !== null) {
        $query .= " AND (rs.department = ? OR s.department = ?)";
        $params[] = $user_department;
        $params[] = $user_department;
    } elseif ($department_filter !== 'all') {
        // Filter by chosen department
        $query .= " AND (rs.department = ? OR s.department = ?)";
        $params[] = $department_filter;
        $params[] = $department_filter;
    }
    $query .= " ORDER BY rs.submission_date DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $research_papers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If we have student_ids referenced, fetch those students in batch to map names/strands
    $studentIds = [];
    $researchIds = [];
    foreach ($research_papers as $r) {
        // treat student_id as valid when it converts to an integer > 0
        if (isset($r['student_id']) && intval($r['student_id']) > 0) {
            $studentIds[] = $r['student_id'];
        }
        $researchIds[] = $r['id'];
    }

    $studentsMap = [];
    if (count($studentIds) > 0) {
        // Unique ids
        $studentIds = array_values(array_unique($studentIds));
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $sstmt = $conn->prepare("SELECT student_id, firstname, department FROM students WHERE student_id IN ($placeholders)");
        $sstmt->execute($studentIds);
        $students = $sstmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($students as $s) {
            $studentsMap[$s['student_id']] = $s;
        }
    }

    // Fetch reviews stats in batch
    $reviewsMap = [];
    if (count($researchIds) > 0) {
        $researchIds = array_values(array_unique($researchIds));
        $placeholders = implode(',', array_fill(0, count($researchIds), '?'));
        $rstmt = $conn->prepare("SELECT research_id, AVG(rating) as avg_rating, COUNT(*) as num_reviews FROM reviews WHERE research_id IN ($placeholders) GROUP BY research_id");
        $rstmt->execute($researchIds);
        $reviewRows = $rstmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($reviewRows as $rr) {
            $reviewsMap[$rr['research_id']] = $rr;
        }
    }

    // Enrich research_papers with computed fields used by the template
    foreach ($research_papers as &$paper) {
        // Only process if uploader_type is set
        if ($paper['uploader_type'] === 'student') {
            if (isset($studentsMap[$paper['student_id']])) {
                // Student found in database
                $paper['firstname'] = $studentsMap[$paper['student_id']]['firstname'];
                // Ensure department present on paper
                if (empty($paper['department'])) {
                    $paper['department'] = $studentsMap[$paper['student_id']]['department'] ?? '';
                }
            } else {
                // Student upload but student record not found
                $paper['firstname'] = 'Student';
                if (empty($paper['department'])) { $paper['department'] = $paper['department'] ?? ''; }
            }
        } else {
            // Admin upload
            $paper['firstname'] = 'Admin';
            // department stays as saved on rs
        }

        // reviews
        $paperId = $paper['id'];
        if (isset($reviewsMap[$paperId])) {
            $paper['avg_rating'] = $reviewsMap[$paperId]['avg_rating'];
            $paper['num_reviews'] = $reviewsMap[$paperId]['num_reviews'];
        } else {
            $paper['avg_rating'] = null;
            $paper['num_reviews'] = 0;
        }
    }

    unset($paper); // break reference


    // Only deduplicate if viewing all departments
    if ($department_filter === 'all') {
        $uniqueMap = [];
        foreach ($research_papers as $paper) {
            if (empty($paper['document']) || empty($paper['title'])) continue;
            $key = strtolower(trim($paper['title'])) . '|' . strtolower(trim(basename($paper['document'])));
            if (!isset($uniqueMap[$key]) || strtotime($paper['submission_date']) > strtotime($uniqueMap[$key]['submission_date'])) {
                $uniqueMap[$key] = $paper;
            }
        }
        $research_papers = array_values($uniqueMap);
    }
    // Sort by submission_date desc
    usort($research_papers, function($a, $b) {
        return strtotime($b['submission_date']) <=> strtotime($a['submission_date']);
    });

    // Apply search filter if present
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search_term = strtolower(trim($_GET['search']));
        $research_papers = array_filter($research_papers, function($paper) use ($search_term) {
            return strpos(strtolower($paper['title']), $search_term) !== false ||
                   strpos(strtolower($paper['members'] ?? ''), $search_term) !== false ||
                   strpos(strtolower($paper['department'] ?? ''), $search_term) !== false ||
                   strpos(strtolower($paper['keywords'] ?? ''), $search_term) !== false;
        });
        $research_papers = array_values($research_papers);
    }

    // Pagination after deduplication and filtering
    $filtered_total_items = count($research_papers);
    $filtered_total_pages = ceil($filtered_total_items / $items_per_page);
    $current_page = min($current_page, max(1, $filtered_total_pages));
    $offset = ($current_page - 1) * $items_per_page;
    $research_papers = array_slice($research_papers, $offset, $items_per_page);
    
    // Update pagination variables for display
    $total_items = $filtered_total_items;
    $total_pages = $filtered_total_pages;

} catch (PDOException $e) {
    // Fallback: just get research without student data
    try {
        $query = "SELECT * FROM research_submission WHERE (status = 1" . ($current_student_id ? " OR student_id = ?" : "") . ") AND year LIKE ?";
        $params = [];
        if ($current_student_id) { $params[] = $current_student_id; }
        $params[] = $__ay_like;
        if ($department_filter !== 'all') {
            $query .= " AND department = ?";
            $params[] = $department_filter;
        }
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $research_papers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($research_papers as &$paper) {
            // Check student_id to determine if it's a student or admin upload
            if (isset($paper['student_id']) && intval($paper['student_id']) > 0) {
                $paper['firstname'] = 'Student';
                $paper['student_strand'] = $paper['paper_strand'] ?? 'N/A';
            } else {
                $paper['firstname'] = 'Admin';
                $paper['student_strand'] = $paper['paper_strand'] ?? 'N/A';
            }
        }
    } catch (Exception $e2) {
        $research_papers = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research Repository | BNHS</title>

    <!-- Google Fonts - Matching Google Scholar -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
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

    <style>
        body {
            font-family: 'Roboto', Arial, sans-serif;
            background-color: #f8f9fa;
            color: #202124;
            line-height: 1.6;
        }
        /* Ensure app sidebar (admin/subadmin/student) is visible inline on desktop */
        @media (min-width: 1024px) {
            #sidebar {
                position: static !important;
                transform: none !important;
            }
        }

        /* Header - Google Scholar Style */
        .scholar-header {
            background-color: white;
            border-bottom: 1px solid #dadce0;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .logo-container .logo {
            display: flex;
            align-items: center;
            font-size: 1.5rem;
            font-weight: 500;
            color: #1a73e8;
            text-decoration: none;
        }

        .logo i {
            margin-right: 8px;
            color: #34a853;
        }

        .search-container {
            flex: 1;
            max-width: 600px;
            margin: 0 24px;
            position: relative;
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #70757a;
        }

        .search-box {
            width: 100%;
            padding: 10px 16px 10px 45px;
            border: 1px solid #dfe1e5;
            border-radius: 24px;
            font-size: 16px;
            outline: none;
            transition: box-shadow 0.3s;
        }

        .search-box:focus {
            box-shadow: 0 1px 6px rgba(32, 33, 36, 0.28);
        }

        /* Sidebar Filters */
        .filters {
            width: 240px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e8e8e8;
            align-self: start;
        }

        .filter-title {
            font-size: 14px;
            font-weight: 500;
            color: #5f6368;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-options li {
            list-style: none;
            padding: 8px 0;
            cursor: pointer;
            color: #1a73e8;
            font-size: 14px;
        }

        .filter-options li:hover {
            text-decoration: underline;
        }

        /* Results List */
        .results {
            flex: 1;
            padding: 20px 0;
        }

        .paper-card {
            background: white;
            border: 1px solid #e8e8e8;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            transition: box-shadow 0.2s;
        }

        .paper-card:hover {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
        }

        .paper-title {
            font-size: 18px;
            font-weight: 500;
            color: #1a0dab;
            text-decoration: none;
        }

        .paper-title:hover {
            text-decoration: underline;
        }

        .paper-meta {
            font-size: 14px;
            color: #70757a;
            margin: 4px 0;
        }

        .paper-abstract {
            font-size: 14px;
            color: #5f6368;
            margin: 8px 0;
            line-height: 1.5;
        }


        .paper-actions {
            font-size: 14px;
            color: #1a73e8;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .paper-actions a {
            display: flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
        }

        .paper-img {
            width: 120px;
            height: 90px;
            object-fit: cover;
            border-radius: 6px;
            margin-left: 16px;
        }


        .paper-row {
            display: flex;
            gap: 16px;
        }

        /* Responsive Styles */
        @media (max-width: 1024px) {
            .container {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            .content-wrapper {
                flex-direction: column;
                gap: 0;
            }
            .filters {
                width: 100%;
                margin-bottom: 20px;
                border-radius: 8px;
                border: 1px solid #e8e8e8;
                box-shadow: 0 1px 2px rgba(0,0,0,0.04);
            }
            .results {
                padding: 10px 0;
            }
        }
        @media (max-width: 768px) {
            .scholar-header {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
                padding: 10px 8px;
            }
            .logo-container {
                justify-content: center;
                margin-bottom: 6px;
            }
            .search-container {
                margin: 0;
                max-width: 100%;
            }
            .filters {
                padding: 12px;
                font-size: 15px;
            }
            .paper-row {
                flex-direction: column;
                gap: 10px;
            }
            .paper-img {
                width: 100%;
                height: 140px;
                margin-left: 0;
                margin-top: 10px;
            }
            .paper-card {
                padding: 10px;
            }
            .paper-title {
                font-size: 16px;
            }
            .paper-meta, .paper-abstract, .paper-actions {
                font-size: 13px;
            }
        }
        @media (max-width: 480px) {
            .scholar-header {
                font-size: 15px;
                padding: 8px 2px;
            }
            .filters {
                font-size: 14px;
                padding: 8px;
            }
            .paper-title {
                font-size: 15px;
            }
            .paper-img {
                height: 100px;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col lg:flex-row">
    <?php
        $__ut = $_SESSION['user_type'] ?? '';
        if ($__ut === 'admin') {
            include 'admin_sidebar.php';
        } elseif ($__ut === 'sub_admins') {
            include 'subadmin_sidebar.php';
        } elseif ($__ut === 'student') {
            include 'student_sidebar.php';
        }
    ?>

    <!-- SweetAlert2 for logout confirmation -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    // Global delegated logout confirmation (guarded to bind once)
    if (!window._logoutConfirmBound) {
      window._logoutConfirmBound = true;
      document.addEventListener('click', function (e) {
        const a = e.target.closest('a[href]');
        if (!a) return;
        const href = a.getAttribute('href') || '';
        const isLogout = href.endsWith('logout.php') || href.endsWith('admin_logout.php');
        if (!isLogout) return;
        e.preventDefault();
        if (typeof Swal === 'undefined') {
          if (confirm('Are you sure you want to sign out?')) {
            window.location.href = href;
          }
          return;
        }
        Swal.fire({
          title: 'Sign out?',
          text: 'Are you sure you want to sign out?\nYou will be logged out of your session.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#d33',
          cancelButtonColor: '#3085d6',
          confirmButtonText: 'Yes, sign out',
          cancelButtonText: 'Cancel',
          reverseButtons: true
        }).then((result) => {
          if (result.isConfirmed) {
            window.location.href = href;
          }
        });
      });
    }
    </script>

    <div class="flex-1 w-full px-4 py-6">
        <!-- Overlay for mobile sidebars (used by student sidebar) -->
        <div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden"></div>
        <div class="content-wrapper flex gap-6">
            <!-- Filters Sidebar -->
            <aside class="filters">
                <h3 class="filter-title"><?= $user_department ? 'YOUR DEPARTMENT' : 'FILTER BY DEPARTMENT' ?></h3>
                <ul class="filter-options">
                    <?php if ($user_department): ?>
                        <li style="font-weight:bold; cursor: default; color:#202124;">
                            <?= htmlspecialchars($user_department) ?>
                        </li>
                    <?php else: ?>
                        <li onclick="filterBy('CCS')" style="font-weight:<?= $department_filter === 'CCS' ? 'bold' : 'normal' ?>">CCS (College of Computer Studies)</li>
                        <li onclick="filterBy('COE')" style="font-weight:<?= $department_filter === 'COE' ? 'bold' : 'normal' ?>">COE (College of Education)</li>
                        <li onclick="filterBy('CBS')" style="font-weight:<?= $department_filter === 'CBS' ? 'bold' : 'normal' ?>">CBS (College of Business Studies)</li>
                        <li onclick="filterBy('Senior High School')" style="font-weight:<?= $department_filter === 'Senior High School' ? 'bold' : 'normal' ?>">Senior High School</li>
                    <?php endif; ?>
                </ul>
                <hr class="my-4">
                <h3 class="filter-title">ACADEMIC YEAR</h3>
                <form method="get" id="schoolYearForm">
                    <!-- Department filter -->
                    <input type="hidden" name="department" value="<?= htmlspecialchars($department_filter) ?>">
                    <div class="flex gap-2 items-center mb-2">
                        <?php $__sy = $__academic_year; ?>
                        <select name="academic_year" class="border rounded px-2 py-1 w-full">
                            <option value="2021-2022" <?= $__sy === '2021-2022' ? 'selected' : '' ?>>2021-2022</option>
                            <option value="2022-2023" <?= $__sy === '2022-2023' ? 'selected' : '' ?>>2022-2023</option>
                            <option value="2023-2024" <?= $__sy === '2023-2024' ? 'selected' : '' ?>>2023-2024</option>
                            <option value="2024-2025" <?= $__sy === '2024-2025' ? 'selected' : '' ?>>2024-2025</option>
                            <option value="2025-2026" <?= $__sy === '2025-2026' ? 'selected' : '' ?>>2025-2026</option>
                        </select>
                    </div>
                    <button type="submit" class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 text-sm">Apply</button>
                </form>
            </aside>

            <!-- Main Content: Search and Results -->
            <div class="flex-1 flex flex-col">
                <div class="flex justify-between items-center mb-2 gap-3 flex-wrap">
                    <form id="searchForm" method="get" action="repository.php" class="relative flex items-center w-full max-w-xl">
                        <input type="hidden" name="department" value="<?= htmlspecialchars($department_filter) ?>">
                        <input type="hidden" name="academic_year" value="<?= isset($_GET['academic_year']) ? htmlspecialchars($_GET['academic_year']) : (isset($_GET['school_year']) ? htmlspecialchars($_GET['school_year']) : '') ?>">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="search-box" name="search" placeholder="Search research papers..." id="searchInput" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" autocomplete="off">
                        <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 bg-blue-500 text-white px-3 py-1 rounded-full hover:bg-blue-600 text-sm">Search</button>
                    </form>
                </div>
                <div class="mb-2 text-right text-gray-700 text-sm">
                    <?php $___fti = isset($filtered_total_items) && $filtered_total_items > 0 ? $filtered_total_items : count($research_papers); ?>
                    Showing <span class="font-semibold"><?= $___fti ?></span> research paper<?= $___fti == 1 ? '' : 's' ?> found
                </div>
                <div class="results" id="results">
                <?php if (count($research_papers) > 0): ?>
                    <?php foreach ($research_papers as $paper): ?>
                        <div class="paper-card" data-title="<?= htmlspecialchars(strtolower($paper['title'] ?? '')) ?>"
                             data-members="<?= htmlspecialchars(strtolower($paper['members'] ?? '')) ?>"
                             data-department="<?= htmlspecialchars(strtolower($paper['department'] ?? '')) ?>"
                             data-keywords="<?= htmlspecialchars(strtolower($paper['keywords'] ?? '')) ?>">
                            <div class="paper-row">
                                <div class="flex-1">
                                    <h3>
                                        <?php
                                        // Document path logic
                                        $docPath = '';
                                        if (!empty($paper['document'])) {
                                            // Remove any leading slashes and normalize path
                                            $cleanPath = ltrim($paper['document'], '/');
                                            
                                            // If path already contains uploads/, use it directly
                                            if (strpos($cleanPath, 'uploads/') === 0) {
                                                $docPath = $cleanPath;
                                            } else {
                                                // Otherwise, prepend uploads/research_documents/
                                                $docPath = 'uploads/research_documents/' . $cleanPath;
                                            }
                                        }
                                        ?>
                                        <?php if (!empty($docPath)): ?>
                                            <a href="<?= htmlspecialchars($docPath) ?>" target="_blank" rel="noopener" class="paper-title" onclick="incrementViewOnly(<?= (int)$paper['id'] ?>)">
                                                <?= htmlspecialchars($paper['title']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="paper-title">
                                                <?= htmlspecialchars($paper['title']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </h3>
                                    <div class="paper-meta">
                                        <?= htmlspecialchars($paper['members']) ?> - <?= htmlspecialchars($paper['year']) ?>
                                        <span class="mx-1">•</span>
                                        <?php 
                                            $deptLabel = $paper['department'] ?? ($paper['paper_strand'] ?? '');
                                            if (!empty($deptLabel)) {
                                                echo 'Department: ' . htmlspecialchars($deptLabel);
                                            } else {
                                                echo "Department not specified";
                                            }
                                        ?>
                                        
                                    </div>
                                    <p class="paper-abstract"><?= htmlspecialchars($paper['abstract']) ?></p>
                                    <?php if (!empty($paper['keywords'])): ?>
                                        <?php 
                                            $kwList = array_filter(array_map('trim', preg_split('/\s*,\s*/', (string)$paper['keywords'])));
                                        ?>
                                        <?php if (count($kwList) > 0): ?>
                                        <div class="mt-2 flex flex-wrap gap-2 text-sm">
                                            <?php foreach ($kwList as $kw): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-100">
                                                    <i class="fas fa-tag mr-1 text-xs"></i> <?= htmlspecialchars($kw) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <div class="paper-actions">
                                        <?php if (!empty($docPath)): ?>
                                            <a href="<?= htmlspecialchars($docPath) ?>" target="_blank" rel="noopener" onclick="incrementViewOnly(<?= (int)$paper['id'] ?>)">
                                                <i class="fas fa-file-pdf"></i> PDF
                                            </a>
                                        <?php endif; ?>
                                        <span title="Views" class="flex items-center gap-1 text-gray-600">
                                            <i class="fas fa-eye"></i> <span id="views-<?= (int)$paper['id'] ?>"><?= (int)$paper['views'] ?></span>
                                        </span>
                                        <a href="#" onclick="openCiteModal(<?= (int)$paper['id'] ?>); return false;" title="Cite">
                                            <i class="fas fa-quote-right"></i> Cite
                                        </a>
                                        <!-- Student-only actions -->
                                        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'student'): ?>
                                            <button class="ml-2 text-blue-500 hover:text-blue-700 focus:outline-none" title="Bookmark this research" onclick="handleBookmarkClick(<?= (int)$paper['id'] ?>)">
                                                <i class="fas fa-bookmark"></i>
                                            </button>
                                        <?php endif; ?>
                                        <span><?= date('M j, Y', strtotime($paper['submission_date'])) ?></span>
                                    </div>
                                    <?php
                                        // Build simple APA-style citation with clickable URL in modal
                                        $authorsApa = '';
                                        if (!empty($paper['members'])) {
                                            $authorsApa = format_authors_apa($paper['members']);
                                        }
                                        $yearText = trim((string)($paper['year'] ?? ''));
                                        if ($yearText === '') { $yearText = 'n.d.'; }
                                        $titleText = trim((string)($paper['title'] ?? 'Untitled'));
                                        $institution = 'Becuran National High School';
                                        $citationCore = trim(($authorsApa ? $authorsApa . '. ' : '') . '(' . $yearText . '). ' . $titleText . '. ' . $institution . '.');
                                        $docUrl = '';
                                        if (!empty($docPath)) {
                                            $docUrl = build_absolute_url($docPath);
                                        }
                                        // Plain text for copy textarea
                                        $citationApa = $citationCore . ($docUrl ? ' ' . $docUrl : '');
                                        // HTML for modal display with clickable URL
                                        if ($docUrl) {
                                            $citationApaHtml = htmlspecialchars($citationCore) . ' <a href="' . htmlspecialchars($docUrl) . '" target="_blank" rel="noopener">' . htmlspecialchars($docUrl) . '</a>';
                                        } else {
                                            $citationApaHtml = htmlspecialchars($citationCore);
                                        }
                                    ?>
                                    <!-- Cite Modal -->
                                    <div id="cite-modal-<?= (int)$paper['id'] ?>" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50">
                                        <div class="bg-white w-11/12 max-w-2xl rounded-lg shadow-lg p-4 sm:p-5 max-h-[85vh] overflow-y-auto">
                                            <div class="flex items-center justify-between mb-3">
                                                <h4 class="text-base sm:text-lg font-semibold flex items-center gap-2"><i class="fas fa-quote-right text-blue-600"></i><span>Citations</span></h4>
                                                <button class="text-gray-500 hover:text-gray-700" onclick="closeCiteModal(<?= (int)$paper['id'] ?>)" title="Close"><i class="fas fa-times"></i></button>
                                            </div>
                                            <div class="mb-2 sm:mb-4 grid grid-cols-1 sm:grid-cols-[3.5rem_1fr] gap-2 sm:gap-4">
                                                <div class="sm:text-right sm:pr-2 text-gray-500 font-medium text-sm">APA</div>
                                                <div class="text-sm leading-6 text-gray-900 break-words">
                                                    <p class="whitespace-normal"><?= $citationApaHtml ?></p>
                                                </div>
                                            </div>
                                            <!-- Hidden textarea for copy to clipboard -->
                                            <textarea id="cite-text-<?= (int)$paper['id'] ?>" class="sr-only" readonly><?= htmlspecialchars($citationApa) ?></textarea>
                                            <div class="flex flex-col sm:flex-row sm:justify-end gap-2 pt-1">
                                                <button class="w-full sm:w-auto px-3 py-2 bg-gray-100 rounded hover:bg-gray-200" onclick="closeCiteModal(<?= (int)$paper['id'] ?>)">Close</button>
                                                <button class="w-full sm:w-auto px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700" onclick="copyCitation(<?= (int)$paper['id'] ?>)" title="Copy citation"><i class="fas fa-copy mr-1"></i>Copy</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Image -->
                                <?php
                                $imagePath = '';
                                if (!empty($paper['image'])) {
                                    // Remove any leading slashes and normalize path
                                    $cleanImagePath = ltrim($paper['image'], '/');
                                    
                                    // If path already contains uploads/, use it directly
                                    if (strpos($cleanImagePath, 'uploads/') === 0) {
                                        $imagePath = $cleanImagePath;
                                    } else {
                                        // Otherwise, prepend uploads/research_images/
                                        $imagePath = 'uploads/research_images/' . $cleanImagePath;
                                    }
                                }
                                ?>
                                <?php if (!empty($imagePath)): ?>
                                    <img src="<?= htmlspecialchars($imagePath) ?>" alt="Research Image" class="paper-img">
                                <?php else: ?>
                                    <div class="paper-img bg-gray-100 flex items-center justify-center">
                                        <i class="fas fa-image text-gray-400"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-folder-open text-6xl mb-4 opacity-50"></i>
                        <h3 class="text-xl">No research papers found</h3>
                        <p>Try adjusting your filter.</p>
                    </div>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="mt-6 flex justify-center items-center gap-2">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=1<?= isset($_GET['department']) ? '&department=' . htmlspecialchars($_GET['department']) : '' ?><?= (isset($_GET['academic_year']) ? '&academic_year=' . urlencode($_GET['academic_year']) : (isset($_GET['school_year']) ? '&academic_year=' . urlencode($_GET['school_year']) : '')) ?><?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>" 
                           class="px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?page=<?= $current_page - 1 ?><?= isset($_GET['department']) ? '&department=' . htmlspecialchars($_GET['department']) : '' ?><?= (isset($_GET['academic_year']) ? '&academic_year=' . urlencode($_GET['academic_year']) : (isset($_GET['school_year']) ? '&academic_year=' . urlencode($_GET['school_year']) : '')) ?><?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>" 
                           class="px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php
                    // Show up to 5 page numbers, centered around current page
                    $start_page = max(1, min($current_page - 2, $total_pages - 4));
                    $end_page = min($total_pages, max($current_page + 2, 5));

                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?page=<?= $i ?><?= isset($_GET['department']) ? '&department=' . htmlspecialchars($_GET['department']) : '' ?><?= (isset($_GET['academic_year']) ? '&academic_year=' . urlencode($_GET['academic_year']) : (isset($_GET['school_year']) ? '&academic_year=' . urlencode($_GET['school_year']) : '')) ?><?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>" 
                           class="px-3 py-1 <?= $i === $current_page ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' ?> rounded hover:bg-blue-600 hover:text-white">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?= $current_page + 1 ?><?= isset($_GET['department']) ? '&department=' . htmlspecialchars($_GET['department']) : '' ?><?= (isset($_GET['academic_year']) ? '&academic_year=' . urlencode($_GET['academic_year']) : (isset($_GET['school_year']) ? '&academic_year=' . urlencode($_GET['school_year']) : '')) ?><?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>" 
                           class="px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?page=<?= $total_pages ?><?= isset($_GET['department']) ? '&department=' . htmlspecialchars($_GET['department']) : '' ?><?= (isset($_GET['academic_year']) ? '&academic_year=' . urlencode($_GET['academic_year']) : (isset($_GET['school_year']) ? '&academic_year=' . urlencode($_GET['school_year']) : '')) ?><?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>" 
                           class="px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>

                    <span class="ml-4 text-sm text-gray-600">
                        Page <?= $current_page ?> of <?= $total_pages ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Search & Filter Script -->
    <script>
        function filterBy(department) {
            const u = new URL(window.location.href);
            u.searchParams.set('department', department);
            // Keep existing search/year params automatically
            window.location.href = u.toString();
        }

        const searchInput = document.getElementById('searchInput');
        const cards = document.querySelectorAll('.paper-card');

        searchInput.addEventListener('input', function () {
            const term = this.value.toLowerCase();

            cards.forEach(card => {
                const title = card.dataset.title;
                const members = card.dataset.members;
                const dept = card.dataset.department;

                if (title.includes(term) || members.includes(term) || dept.includes(term)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Citation modal helpers
        function openCiteModal(paperId) {
            const m = document.getElementById(`cite-modal-${paperId}`);
            if (!m) return;
            m.classList.remove('hidden');
            m.classList.add('flex');
        }

        function closeCiteModal(paperId) {
            const m = document.getElementById(`cite-modal-${paperId}`);
            if (!m) return;
            m.classList.add('hidden');
            m.classList.remove('flex');
        }

        async function copyCitation(paperId) {
            const ta = document.getElementById(`cite-text-${paperId}`);
            if (!ta) return;
            try {
                await navigator.clipboard.writeText(ta.value);
                // Simple feedback
                const original = ta.value;
                ta.value = original + '\n\n(Copied to clipboard)';
                setTimeout(() => { ta.value = original; }, 800);
            } catch (e) {
                ta.select();
                document.execCommand('copy');
            }
        }

        // Bookmark helpers
        function handleBookmarkClick(researchId) {
            bookmarkResearch(researchId);
        }

        function bookmarkResearch(researchId) {
            fetch('bookmark_research.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'research_id=' + encodeURIComponent(researchId)
            })
            .then(response => response.json())
            .then(result => {
                if (typeof Swal === 'undefined') {
                    // Fallback if SweetAlert2 failed to load
                    if (result.success) {
                        alert('Bookmarked successfully!');
                    } else {
                        alert(result.message || 'Failed to bookmark.');
                    }
                    return;
                }
                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Bookmarked!',
                        text: 'The research has been added to your bookmarks.',
                        confirmButtonText: 'OK'
                    });
                } else {
                    Swal.fire({
                        icon: 'info',
                        title: 'Notice',
                        text: (result.message || 'Failed to bookmark.'),
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(() => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error bookmarking. Please try again.',
                        confirmButtonText: 'OK'
                    });
                } else {
                    alert('Error bookmarking.');
                }
            });
        }

        // Increment views only (used when links open in new tab)
        function incrementViewOnly(researchId) {
            try {
                fetch('increment_view.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'research_id=' + encodeURIComponent(researchId)
                }).catch(() => {});
            } catch (e) {}
            const vc = document.getElementById('views-' + researchId);
            if (vc) {
                const n = parseInt(vc.textContent || '0', 10);
                if (!Number.isNaN(n)) vc.textContent = (n + 1).toString();
            }
        }
    </script>
</body>
</html>