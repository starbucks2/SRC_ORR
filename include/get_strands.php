<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
try {
    // Prefer strand_id, fallback to id if not migrated
    $sql = 'SELECT '; 
    try {
        $stmt = $conn->query('SELECT strand_id AS id, strand, department_id FROM strands ORDER BY strand');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e1) {
        $stmt = $conn->query('SELECT id AS id, strand, NULL AS department_id FROM strands ORDER BY strand');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode(['ok'=>true,'data'=>$rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
