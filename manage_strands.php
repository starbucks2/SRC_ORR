<?php
session_start();
require_once 'db.php';

// Admin only
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['error'] = 'Only administrators can manage strands.';
    header('Location: admin_dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update student strand (store in students.course_strand for SHS)
        if (isset($_POST['update_student'])) {
            $studentId = trim($_POST['student_id'] ?? '');
            $strand = trim($_POST['student_strand'] ?? '');
            if ($studentId !== '' && $strand !== '') {
                $stmt = $conn->prepare('SELECT student_id FROM students WHERE student_id = ?');
                $stmt->execute([$studentId]);
                if ($stmt->fetch()) {
                    $upd = $conn->prepare('UPDATE students SET course_strand = ? WHERE student_id = ?');
                    $upd->execute([$strand, $studentId]);
                    $_SESSION['success'] = 'Student strand updated successfully.';
                } else {
                    $_SESSION['error'] = 'Student not found.';
                }
            }
        }

        // Update research adviser strand via employees table (canonical)
        if (isset($_POST['update_subadmin'])) {
            $subadminId = trim($_POST['subadmin_id'] ?? '');
            $strand = trim($_POST['subadmin_strand'] ?? '');
            if ($subadminId !== '' && $strand !== '') {
                $stmt = $conn->prepare("SELECT employee_id FROM employees WHERE employee_id = ? AND employee_type = 'RESEARCH_ADVISER'");
                $stmt->execute([$subadminId]);
                if ($stmt->fetch()) {
                    // Store chosen strand name in employees.department for SHS context or a dedicated JSON in permissions if needed.
                    $upd = $conn->prepare('UPDATE employees SET department = ? WHERE employee_id = ?');
                    $upd->execute([$strand, $subadminId]);
                    $_SESSION['success'] = 'Research adviser strand updated successfully.';
                } else {
                    $_SESSION['error'] = 'Research adviser not found.';
                }
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error updating strand: ' . $e->getMessage();
    }
}

header('Location: update_strands.php');
exit();
?>
