<?php
session_start();
include 'db.php';

// Determine department and course/strand based on session (students and sub-admins restricted) or URL
$user_department = null;
$user_course_strand = null;
if (isset($_SESSION['user_type']) && !empty($_SESSION['department']) &&
    (($_SESSION['user_type'] === 'student') || ($_SESSION['user_type'] === 'sub_admins'))) {
    $user_department = $_SESSION['department'];
    $user_course_strand = $_SESSION['course_strand'] ?? null;
    $department_filter = $user_department; // force to user's department
} else {
    // Others can use URL filter (accept legacy strand for compatibility)
    $department_filter = $_GET['department'] ?? ($_GET['strand'] ?? 'all');
}

// Course/Strand filter from URL
$course_strand_filter = $_GET['course_strand'] ?? 'all';

$research_papers = [];
// If a student is logged in, allow them to see their own submissions regardless of status
$current_student_id = (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'student' && isset($_SESSION['student_id']))
    ? $_SESSION['student_id']
    : null;

// Ensure these exist even if an exception path skips assignments later
$filtered_total_items = 0;
$filtered_total_pages = 0;
$total_items = 0;
$total_pages = 1;
