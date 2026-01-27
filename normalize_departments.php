<?php
session_start();
include 'db.php';

date_default_timezone_set('Asia/Manila');

// Allow only admins to run this normalization
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo 'Forbidden: Admin login required.';
    exit();
}

function canon_dept($val) {
    $v = strtolower(trim((string)$val));
    $map = [
        'ccs' => 'CCS', 'college of computer studies' => 'CCS', 'computer studies' => 'CCS',
        'cbs' => 'CBS', 'college of business studies' => 'CBS', 'business studies' => 'CBS',
        'coe' => 'COE', 'college of education' => 'COE', 'education' => 'COE',
        'senior high school' => 'Senior High School', 'shs' => 'Senior High School', 'senior high' => 'Senior High School'
    ];
    return $map[$v] ?? '';
}

function infer_dept_from_strand($strand) {
    $s = strtolower(trim((string)$strand));
    // CCS
    $ccs = ['bsis','ict','it','comsci','cs'];
    if (in_array($s, $ccs, true)) return 'CCS';
    // CBS
    $cbs = ['ais','entrepreneurship','accountancy','marketing','business'];
    if (in_array($s, $cbs, true)) return 'CBS';
    // COE
    $coe = ['beed','bsed english','bsed','education'];
    if (in_array($s, $coe, true)) return 'COE';
    // SHS
    $shs = ['stem','gas','humss','abm','shs'];
    if (in_array($s, $shs, true)) return 'Senior High School';
    return '';
}

function infer_dept_from_permissions($permText) {
    $perms = is_array($permText) ? $permText : (json_decode((string)$permText, true) ?: []);
    $perms = array_map('strtolower', $perms);
    $keys = ['ccs' => 'CCS', 'cbs' => 'CBS', 'coe' => 'COE', 'senior high school' => 'Senior High School', 'shs' => 'Senior High School'];
    foreach ($perms as $p) {
        foreach ($keys as $k => $label) {
            if (str_ends_with($p, '_' . $k)) return $label;
        }
    }
    return '';
}

$results = [
    'sub_admins_set_from_permissions' => 0,
    'sub_admins_normalized' => 0,
    'students_set_from_strand' => 0,
    'students_normalized' => 0,
    'research_set_from_students' => 0,
    'research_normalized' => 0,
];

try {
    // 1) Normalize sub_admins.department values to canonical
    $stmt = $conn->query("SELECT id, department, permissions, strand FROM sub_admins");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $dept = $r['department'] ?? '';
        $canon = canon_dept($dept);
        if ($canon === '') {
            // Try legacy strand
            $canon = canon_dept($r['strand'] ?? '');
        }
        if ($canon === '') {
            // Infer from permissions JSON
            $canon = infer_dept_from_permissions($r['permissions'] ?? '');
        }
        if ($canon !== '' && $canon !== (string)$dept) {
            $upd = $conn->prepare("UPDATE sub_admins SET department = ? WHERE id = ?");
            $upd->execute([$canon, $r['id']]);
            if (empty($dept)) $results['sub_admins_set_from_permissions']++;
            else $results['sub_admins_normalized']++;
        }
    }

    // 2) Normalize students.department; if empty, infer from strand
    $stmt = $conn->query("SELECT student_id, department, strand FROM students");
    $srows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($srows as $r) {
        $dept = $r['department'] ?? '';
        $canon = canon_dept($dept);
        if ($canon === '' && !empty($r['strand'])) {
            $canon = infer_dept_from_strand($r['strand']);
        }
        if ($canon !== '' && $canon !== (string)$dept) {
            $upd = $conn->prepare("UPDATE students SET department = ? WHERE student_id = ?");
            $upd->execute([$canon, $r['student_id']]);
            if (empty($dept)) $results['students_set_from_strand']++;
            else $results['students_normalized']++;
        }
    }

    // 3) Normalize research_submission.department; if empty, copy from joined student
    // 3a) Normalize existing values
    $stmt = $conn->query("SELECT id, department FROM research_submission");
    $rrows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rrows as $r) {
        $dept = $r['department'] ?? '';
        $canon = canon_dept($dept);
        if ($canon !== '' && $canon !== (string)$dept) {
            $upd = $conn->prepare("UPDATE research_submission SET department = ? WHERE id = ?");
            $upd->execute([$canon, $r['id']]);
            $results['research_normalized']++;
        }
    }

    // 3b) Fill missing department from students
    $sql = "UPDATE research_submission rs 
            JOIN students s ON rs.student_id = s.student_id 
            SET rs.department = s.department 
            WHERE (rs.department IS NULL OR rs.department = '') AND s.department IS NOT NULL AND s.department <> ''";
    $aff = $conn->exec($sql);
    if ($aff !== false) { $results['research_set_from_students'] += (int)$aff; }

} catch (PDOException $e) {
    http_response_code(500);
    echo 'Normalization error: ' . htmlspecialchars($e->getMessage());
    exit();
}

// Simple output
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Department Normalization</title>
<style>
 body { font-family: system-ui, Arial, sans-serif; padding: 20px; }
 .ok { color: #065f46; }
 .box { border: 1px solid #ddd; padding: 12px; border-radius: 8px; max-width: 640px; }
</style>
</head>
<body>
    <h2>Department Normalization Completed</h2>
    <div class="box">
        <p class="ok">sub_admins set from permissions: <strong><?= (int)$results['sub_admins_set_from_permissions'] ?></strong></p>
        <p class="ok">sub_admins normalized labels: <strong><?= (int)$results['sub_admins_normalized'] ?></strong></p>
        <hr>
        <p class="ok">students set from strand: <strong><?= (int)$results['students_set_from_strand'] ?></strong></p>
        <p class="ok">students normalized labels: <strong><?= (int)$results['students_normalized'] ?></strong></p>
        <hr>
        <p class="ok">research set from students: <strong><?= (int)$results['research_set_from_students'] ?></strong></p>
        <p class="ok">research normalized labels: <strong><?= (int)$results['research_normalized'] ?></strong></p>
    </div>
    <p>Return to <a href="admin_dashboard.php">Admin Dashboard</a> or <a href="subadmin_dashboard.php">Sub-admin Dashboard</a>.</p>
</body>
</html>
