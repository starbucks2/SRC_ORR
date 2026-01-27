<?php
include 'db.php';
if (isset($_POST['research_id']) && is_numeric($_POST['research_id'])) {
    $id = (int)$_POST['research_id'];
    $stmt = $conn->prepare('UPDATE research_submission SET views = views + 1 WHERE id = ?');
    $stmt->execute([$id]);
}
?>
