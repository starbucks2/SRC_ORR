<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 if you want to see errors in browser

// Start session and include database
session_start();

// Helper to get base URL (prefer .env APP_BASE_URL, else auto-detect)
function get_base_url() {
    // Load minimal .env if present
    if (file_exists(__DIR__ . '/.env')) {
        $lines = @file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
                list($k, $v) = explode('=', $line, 2);
                $k = trim($k); $v = trim(trim($v), "'\"");
                if (!isset($_ENV[$k])) $_ENV[$k] = $v;
            }
        }
    }
    $envUrl = $_ENV['APP_BASE_URL'] ?? $_ENV['SITE_URL'] ?? '';
    if ($envUrl) {
        return rtrim($envUrl, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
          || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

// Check if db.php exists
if (!file_exists('db.php')) {
    echo json_encode([
        'success' => false,
        'message' => 'Database configuration file not found',
        'debug' => 'db.php file missing'
    ]);
    exit();
}

try {
    include 'db.php';
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'debug' => $e->getMessage()
    ]);
    exit();
}

// Check if database connection exists
if (!isset($conn)) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection not established',
        'debug' => '$conn variable not found'
    ]);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false, 
        'message' => 'Method not allowed'
    ]);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// If JSON decode fails, try form data
if (!$input) {
    $input = $_POST;
}

// Validate input
if (!$input || !isset($input['email'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Email is required',
        'debug' => 'No email provided in request'
    ]);
    exit();
}

$email = trim($input['email']);

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid email format'
    ]);
    exit();
}

try {
    // Only allow student resets (temporarily)
    $stmt = $conn->prepare("SELECT student_id, firstname FROM students WHERE email = ?");
    $stmt->execute([$email]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        $tokenData = [
            'student_id' => $student['student_id'],
            'email' => $email,
            'expiry' => time() + 3600
        ];

        $secretKey = 'MySecureK3y2024_Bnhs_P@sswordR3set!_ChangeTh1sInProduction';
        $token = base64_encode(json_encode($tokenData));
        $encryptedToken = base64_encode($token . '|' . hash_hmac('sha256', $token, $secretKey));

        $baseUrl = get_base_url();
        $reset_link = $baseUrl . '/reset_password.php?token=' . urlencode($encryptedToken);
        $logo_url = $baseUrl . '/Bnhslogo.png';

        $emailData = [
            'to_email' => $email,
            'to_name' => $student['firstname'],
            'user_firstname' => $student['firstname'],
            'reset_link' => $reset_link,
            'logo_url' => $logo_url,
            'expiry_time' => '1 hour'
        ];

        error_log("Password reset token generated for student ID: " . $student['student_id']);

        echo json_encode([
            'success' => true,
            'send_email' => true,
            'email_data' => $emailData,
            'message' => 'Reset token generated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'send_email' => false,
            'message' => 'If your email is registered, you will receive password reset instructions.'
        ]);
        error_log("Password reset attempt for non-existent email: " . $email);
    }
    
} catch (PDOException $e) {
    // Database error
    error_log("Database error in forgot_password_ajax.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.',
        'debug' => 'PDO Error: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    // General error
    error_log("General error in forgot_password_ajax.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again later.',
        'debug' => 'General Error: ' . $e->getMessage()
    ]);
}

// Close database connection
$conn = null;
?>