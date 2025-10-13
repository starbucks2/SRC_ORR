<?php
// config/mail.php
// Centralized mail configuration for PHPMailer, suitable for shared hosting
// Values are read from environment variables when available.

// Helper to read env with fallback
$env = function($key, $default = null) {
    if (isset($_ENV[$key])) return $_ENV[$key];
    $val = getenv($key);
    return $val !== false ? $val : $default;
};

return [
    // Application base URL, e.g. https://yourdomain.com or https://yourdomain.com/Becuran
    // If not set, code will fall back to current host dynamically.
    'app_url'   => rtrim($env('APP_URL', 'https://bnhsresearchhub.com'), '/'),

    // SMTP server settings
    'host'      => $env('SMTP_HOST', 'smtp.gmail.com'),
    'port'      => (int) $env('SMTP_PORT', 587),
    'smtp_auth' => filter_var($env('SMTP_AUTH', 'true'), FILTER_VALIDATE_BOOLEAN),
    'smtp_secure' => strtolower((string) $env('SMTP_SECURE', 'tls')), // tls|ssl

    // Credentials
    'username'  => $env('SMTP_USERNAME', 'juntillaroy@gmail.com'),
    'password'  => $env('SMTP_PASSWORD', 'keto nfia djvw lpy'),

    // From identity
    'from_email' => $env('MAIL_FROM_ADDRESS', $env('SMTP_USERNAME', 'juntillaroy@gmail.com')),
    'from_name'  => $env('MAIL_FROM_NAME', 'BNHS Research System'),
];
