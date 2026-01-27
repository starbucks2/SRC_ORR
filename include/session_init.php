<?php
// Unified secure session initialization for PHP 8.1+
// Start output buffering early to avoid header issues
if (!ob_get_level()) { ob_start(); }

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Harden session behavior
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');
    // Configure cookie params with SameSite and Secure flag when HTTPS
    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $params = session_get_cookie_params();
    @session_set_cookie_params([
        'lifetime' => 0,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Optional: prevent cache for sensitive pages
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

// Provide a minimal redirect helper if not already defined elsewhere
if (!function_exists('rr_redirect')) {
    function rr_redirect($target) {
        if (session_status() === PHP_SESSION_ACTIVE) { @session_write_close(); }
        while (ob_get_level() > 0) { @ob_end_clean(); }
        if (!headers_sent()) {
            header('Location: ' . $target);
            exit();
        }
        echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES) . '"><script>location.replace(' . json_encode($target) . ');</script></head><body>Redirecting...</body></html>';
        exit();
    }
}
