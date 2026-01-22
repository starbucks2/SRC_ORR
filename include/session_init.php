<?php
// Unified secure session initialization for PHP 8.1+
// Start output buffering early to avoid header issues
if (!ob_get_level()) { ob_start(); }

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Harden session behavior
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');
    // Use an app-specific session name to avoid collisions on shared hosting
    @ini_set('session.name', 'SRCORRSESSID');
    // Ensure a writable session save path on shared hosting
    try {
        $root = dirname(__DIR__); // project root
        $sessDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
        if (!is_dir($sessDir)) { @mkdir($sessDir, 0775, true); }
        if (is_dir($sessDir) && is_writable($sessDir)) {
            @session_save_path($sessDir);
        }
    } catch (Throwable $_) { /* ignore and use default */ }
    // Configure cookie params with SameSite and Secure flag
    // Detect HTTPS behind proxies and on known production host
    $xfp = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($xfp === 'https') || (strpos($host, 'src.edu.ph') !== false);
    $params = session_get_cookie_params();
    // Compute cookie path to the app base directory (e.g., /src_orr/)
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? '/')), '/');
    if ($dir === '') { $dir = '/'; }
    if ($dir !== '/') { $dir .= '/'; }
    @session_set_cookie_params([
        'lifetime' => 0,
        'path' => $dir ?: ($params['path'] ?? '/'),
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

// Global safe redirect helper in case a page doesn't declare its own
if (!function_exists('rr_redirect')) {
    function rr_redirect($target) {
        // Write session data before redirect
        if (session_status() === PHP_SESSION_ACTIVE) { @session_write_close(); }
        // Attempt to log redirect for debugging
        try {
            $root = dirname(__DIR__);
            @file_put_contents($root . '/auth_redirect.log', date('c') . " redirect to: " . $target . "\n", FILE_APPEND);
        } catch (Throwable $_) { /* ignore */ }
        // Clear buffers
        while (ob_get_level() > 0) { @ob_end_clean(); }
        if (!headers_sent()) {
            header('Location: ' . $target);
            exit();
        }
        echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES) . '"><script>location.replace(' . json_encode($target) . ');</script></head><body>Redirecting...</body></html>';
        exit();
    }
}
