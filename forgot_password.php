<?php
include __DIR__ . '/include/session_init.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- 🔥 UPDATED: Latest EmailJS SDK -->
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            /* Match login/register: subtle white overlay over background image */
            background-image: linear-gradient(rgba(255, 255, 255, 0.3), rgba(255, 255, 255, 0.3)), url('SRC-Pics.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        .overlay {
            /* Match login/register card opacity */
            background-color: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
        }
        .loading { display: none; }
        .spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 2s linear infinite;
            display: inline-block;
            margin-right: 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .debug-info {
            max-height: 200px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="overlay p-8 rounded-lg shadow-md w-full max-w-md">
        <div class="flex justify-center mb-4">
            <img src="srclogo.png" alt="Logo" class="w-16 h-16">
        </div>
        <h2 class="text-2xl font-bold mb-4 text-center text-gray-800">Forgot Password</h2>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
            </div>
        <?php endif; ?>

        


        <form id="forgot-password-form">
            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" id="email" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md" required>
            </div>

            <button type="submit" id="submit-btn" class="w-full bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600">
                <span id="btn-text">Send Reset Link</span>
                <div id="loading" class="loading">
                    <div class="spinner"></div>
                    Sending...
                </div>
            </button>
        </form>

        <p class="mt-4 text-center text-sm text-gray-600">
            Remember your password? <a href="login.php" class="text-blue-500 hover:text-blue-700">Login here</a>
        </p>
    </div>

    <script>
        // 🔥 UPDATED: EmailJS Configuration - UPDATE THESE VALUES
        const EMAILJS_CONFIG = {
            PUBLIC_KEY: "F-iBl59XLpSPPLfa0",    // Your Public Key
            SERVICE_ID: "service_13a10yj",       // Your Service ID  
            TEMPLATE_ID: "template_1ay2pbt"      // Your Template ID
        };

        // Get detailed error information
        function getDetailedErrorInfo(error) {
            let errorInfo = '';

            if (error.status) {
                errorInfo += `HTTP Status: ${error.status}<br>`;
            }

            if (error.text) {
                errorInfo += `Error Message: ${error.text}<br>`;
            }

            if (error.message) {
                errorInfo += `Details: ${error.message}<br>`;
            }

            // Common error meanings
            if (error.status === 400) {
                errorInfo += '💡 This usually means template parameters don\'t match<br>';
            } else if (error.status === 401) {
                errorInfo += '💡 This usually means invalid Public Key or Service ID<br>';
            } else if (error.status === 404) {
                errorInfo += '💡 This usually means Service ID or Template ID not found<br>';
            } else if (error.status === 402) {
                errorInfo += '💡 This usually means you\'ve hit your monthly limit<br>';
            } else if (error.status === 418) {
                errorInfo += '💡 SDK version is unsupported - should be fixed now!<br>';
            }

            errorInfo += `Full Error: ${JSON.stringify(error, null, 2)}`;

            return errorInfo;
        }

        // Main form submission handler
        document.getElementById('forgot-password-form').addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('submit-btn');
            const btnText = document.getElementById('btn-text');
            const loading = document.getElementById('loading');
            const emailInput = document.getElementById('email');

            // Show loading state
            btnText.style.display = 'none';
            loading.style.display = 'inline-block';
            submitBtn.disabled = true;

            // No inline debug UI

            console.log('📧 Submitting email:', emailInput.value);

            

            // Send AJAX request to PHP
            fetch('forgot_password_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: emailInput.value
                })
            })
            .then(response => {
                console.log('🔄 PHP Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('📨 PHP Response data:', data);

                if (data.success && data.send_email && data.email_data) {
                    // Send email using EmailJS
                    console.log('📮 Sending email via EmailJS...');
                    

                    emailjs.send(EMAILJS_CONFIG.SERVICE_ID, EMAILJS_CONFIG.TEMPLATE_ID, {
                        to_email: data.email_data.to_email,
                        to_name: data.email_data.to_name,
                        user_firstname: data.email_data.user_firstname,
                        reset_link: data.email_data.reset_link,
                        logo_url: data.email_data.logo_url,
                        expiry_time: data.email_data.expiry_time || '1 hour'
                    })
                    .then(function(response) {
                        console.log('✅ Email sent successfully!', response);
                        // SweetAlert2 success toast
                        Swal.fire({
                            icon: 'success',
                            title: 'Email sent',
                            text: 'Password reset instructions have been sent to your email.',
                            confirmButtonColor: '#2563eb'
                        });
                        document.getElementById('forgot-password-form').reset();
                    })
                    .catch(function(error) {
                        console.error('❌ EmailJS Error:', error);
                        // SweetAlert2 error alert
                        Swal.fire({
                            icon: 'error',
                            title: 'Email failed',
                            text: 'Failed to send email. Please try again.',
                            confirmButtonColor: '#ef4444'
                        });
                    });

                } else if (data.success && data.send_email === false) {
                    // Explicit feedback when email is not found
                    Swal.fire({
                        icon: 'error',
                        title: 'Email not found',
                        text: 'Your email is not registered in the system.',
                        confirmButtonColor: '#ef4444'
                    });

                } else {
                    // Show error
                    console.error('❌ PHP Error:', data.message, data.debug || '');
                    // SweetAlert2 error alert
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'An error occurred. Please try again.',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                console.error('❌ Network/Fetch Error:', error);
                // SweetAlert2 network error
                Swal.fire({
                    icon: 'error',
                    title: 'Network error',
                    text: 'Please check your connection and try again.',
                    confirmButtonColor: '#ef4444'
                });
            })
            .finally(() => {
                // Reset button state
                btnText.style.display = 'inline';
                loading.style.display = 'none';
                submitBtn.disabled = false;
            });
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const emailjsLoaded = typeof emailjs !== 'undefined';
            console.log('📚 EmailJS Library Status:', emailjsLoaded ? 'Loaded' : 'Not loaded');

            if (emailjsLoaded) {
                try {
                    emailjs.init({
                        publicKey: EMAILJS_CONFIG.PUBLIC_KEY,
                    });
                    console.log('✅ EmailJS initialized');
                } catch (e) {
                    console.error('❌ EmailJS init error:', e);
                }
            }
        });
    </script>
</body>
</html>