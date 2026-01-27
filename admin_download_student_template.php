<?php
session_start();

// Restrict to admins
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo 'Forbidden';
    exit();
}

$filename = 'student_import_template.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');

$headers = ['lrn','lastname','firstname','middlename','suffix','strand','section'];
fputcsv($out, $headers);

// Example row (Grade is auto-set to "Grade 12" during import)
$sample = [
    '123456789012',     // LRN (12 digits)
    'Dela Cruz',        // Last Name
    'Juan',             // First Name
    'Santos',           // Middle Name (optional)
    '',                 // Suffix (optional)
    'STEM',             // Strand (TVL, STEM, HUMSS, ABM)
    'David'             // Section (must match the strand's allowed sections)
];
fputcsv($out, $sample);

fclose($out);
exit();
