<?php
// Marks activity logs as viewed for the current admin session
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
$_SESSION['logs_last_view'] = date('Y-m-d H:i:s');
// Sticky flag to keep the badge hidden for the rest of this session (even if time comparisons differ)
$_SESSION['logs_viewed_ack'] = true;
echo json_encode(['status' => 'ok', 'viewed_at' => $_SESSION['logs_last_view']]);
