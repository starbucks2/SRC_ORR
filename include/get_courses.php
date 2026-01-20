<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
$deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
if ($deptId <= 0) { echo json_encode(['ok'=>true,'data'=>[]]); exit; }
try {
    $stmt = $conn->prepare("SELECT course_id AS id, course_name AS name, course_code AS code FROM courses WHERE department_id = ? AND is_active = 1 ORDER BY course_name");
    $stmt->execute([$deptId]);
    echo json_encode(['ok'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
