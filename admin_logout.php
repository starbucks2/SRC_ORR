<?php
include __DIR__ . '/include/session_init.php';

// Unset all session variables
$_SESSION = [];

// Delete the session cookie if set
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Finally destroy the session
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

header('Location: login.php');
exit();
?>
