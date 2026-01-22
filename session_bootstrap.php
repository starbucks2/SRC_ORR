<?php
// Auto-prepended bootstrap to ensure secure session init on every request
// Works with .user.ini: auto_prepend_file = session_bootstrap.php
// Resolves include path relative to project root to avoid host path quirks
$init = __DIR__ . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'session_init.php';
if (is_file($init)) {
    require_once $init;
}
