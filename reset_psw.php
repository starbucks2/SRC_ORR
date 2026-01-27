<?php session_start() ;
include('db.php');

// Deprecated: redirect to new reset_password.php while preserving token
$token = isset($_GET['token']) ? $_GET['token'] : (isset($_POST['token']) ? $_POST['token'] : '');
if ($token) {
    header('Location: reset_password.php?token=' . urlencode($token), true, 301);
} else {
    header('Location: forgot_password.php', true, 301);
}
exit();

// Validate reset token before showing the form
$token = isset($_GET['token']) ? $_GET['token'] : (isset($_POST['token']) ? $_POST['token'] : '');
$emailForToken = null;

if ($token) {
    // Use mysqli connection for consistency with rest of code
    include('connect/connection.php');
    $tokenEsc = mysqli_real_escape_string($connect, $token);
    $res = mysqli_query($connect, "SELECT email FROM password_resets WHERE token='" . $tokenEsc . "' AND expires_at > NOW() LIMIT 1");
    if ($res && mysqli_num_rows($res) === 1) {
        $row = mysqli_fetch_assoc($res);
        $emailForToken = $row['email'];
    } else {
        echo '<script>alert("Your password reset link is invalid or has expired. Please request a new one."); window.location.replace("recover_psw.php");</script>';
        exit;
    }
} else {
    echo '<script>alert("Missing password reset token."); window.location.replace("recover_psw.php");</script>';
    exit;
}
?>
<link href="//maxcdn.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css" rel="stylesheet" id="bootstrap-css">
<script src="//maxcdn.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<!------ Include the above in your HEAD tag ---------->

<!doctype html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Fonts -->
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Raleway:300,400,600" rel="stylesheet" type="text/css">

    <link rel="stylesheet" href="style.css">

    <link rel="icon" href="Favicon.png">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css" />

    <title>Login Form</title>
</head>
<body style="
    min-height: 100vh;
    background: url('School.jpg') no-repeat center center fixed;
    background-size: cover;
">

<nav class="navbar navbar-expand-lg navbar-light navbar-laravel" style="background: rgba(255,255,255,0.7);">
    <div class="container">
        <a class="navbar-brand" href="#">Password Reset Form</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
    </div>
</nav>

<main class="login-form">
    <div class="container" style="min-height: 90vh; display: flex; align-items: center; justify-content: center;">
        <div class="row justify-content-center w-100">
            <div class="col-md-8">
                <div class="card" style="background: rgba(255,255,255,0.85); box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); border-radius: 20px;">
                    <div class="card-header text-center" style="background: rgba(255,255,255,0.3); font-weight: bold; font-size: 1.3rem; border-top-left-radius: 20px; border-top-right-radius: 20px;">Reset Your Password</div>
                    <div class="card-body">
                        <form action="#" method="POST" name="login">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="form-group row">
                                <label for="password" class="col-md-4 col-form-label text-md-right">New Password</label>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <input type="password" id="password" class="form-control" name="password" required autofocus minlength="8" placeholder="At least 8 characters">
                                        <div class="input-group-append">
                                            <span class="input-group-text bg-white" style="cursor:pointer; border-left:0;" id="togglePassword">
                                                <i class="bi bi-eye-slash" id="togglePasswordIcon"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="confirm_password" class="col-md-4 col-form-label text-md-right">Confirm Password</label>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <input type="password" id="confirm_password" class="form-control" name="confirm_password" required minlength="8" placeholder="Re-enter your password">
                                        <div class="input-group-append">
                                            <span class="input-group-text bg-white" style="cursor:pointer; border-left:0;" id="toggleConfirmPassword">
                                                <i class="bi bi-eye-slash" id="toggleConfirmPasswordIcon"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <small id="passwordHelp" class="form-text text-danger d-none">Passwords do not match.</small>
                                </div>
                            </div>
                            <div class="col-md-6 offset-md-4 mt-3">
                                <input type="submit" value="Reset" name="reset" class="btn btn-primary btn-block" style="border-radius: 20px;">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
<?php
    if(isset($_POST["reset"])){
        include('connect/connection.php');
        $psw = $_POST["password"] ?? '';
        $confirm = $_POST["confirm_password"] ?? '';
        $tokenPost = $_POST['token'] ?? '';

        // Server-side validation
        if (strlen($psw) < 8) {
            echo '<script>alert("Password must be at least 8 characters long."); history.back();</script>';
            exit;
        }
        if ($psw !== $confirm) {
            echo '<script>alert("Passwords do not match. Please re-enter them."); history.back();</script>';
            exit;
        }

        if (!$tokenPost) {
            echo '<script>alert("Missing token. Please use the reset link from your email."); window.location.replace("recover_psw.php");</script>';
            exit;
        }

        $tokenEsc = mysqli_real_escape_string($connect, $tokenPost);
        $res = mysqli_query($connect, "SELECT email FROM password_resets WHERE token='" . $tokenEsc . "' AND expires_at > NOW() LIMIT 1");
        if (!$res || mysqli_num_rows($res) !== 1) {
            echo '<script>alert("Your password reset link is invalid or has expired. Please request a new one."); window.location.replace("recover_psw.php");</script>';
            exit;
        }

        $row = mysqli_fetch_assoc($res);
        $Email = $row['email'];

        $hash = password_hash($psw, PASSWORD_DEFAULT);

        // Update password for the email
        $emailEsc = mysqli_real_escape_string($connect, $Email);
        $hashEsc = mysqli_real_escape_string($connect, $hash);
        mysqli_query($connect, "UPDATE students SET password='" . $hashEsc . "' WHERE email='" . $emailEsc . "'");

        // Delete used token
        mysqli_query($connect, "DELETE FROM password_resets WHERE token='" . $tokenEsc . "' OR email='" . $emailEsc . "'");

        ?>
        <script>
            alert("Your Password has been Successfully Reset");
            window.location.replace("login.php");
        </script>
        <?php
    }

?>
<script>
    const toggle = document.getElementById('togglePassword');
    const toggleIcon = document.getElementById('togglePasswordIcon');
    const password = document.getElementById('password');
    const toggle2 = document.getElementById('toggleConfirmPassword');
    const toggleIcon2 = document.getElementById('toggleConfirmPasswordIcon');
    const confirmPassword = document.getElementById('confirm_password');
    const help = document.getElementById('passwordHelp');

    toggle.addEventListener('click', function(){
        if(password.type === "password"){
            password.type = 'text';
            toggleIcon.classList.remove('bi-eye-slash');
            toggleIcon.classList.add('bi-eye');
        }else{
            password.type = 'password';
            toggleIcon.classList.remove('bi-eye');
            toggleIcon.classList.add('bi-eye-slash');
        }
    });

    toggle2.addEventListener('click', function(){
        if(confirmPassword.type === "password"){
            confirmPassword.type = 'text';
            toggleIcon2.classList.remove('bi-eye-slash');
            toggleIcon2.classList.add('bi-eye');
        }else{
            confirmPassword.type = 'password';
            toggleIcon2.classList.remove('bi-eye');
            toggleIcon2.classList.add('bi-eye-slash');
        }
    });

    function validatePasswords(){
        if (confirmPassword.value && password.value !== confirmPassword.value) {
            help.classList.remove('d-none');
            return false;
        } else {
            help.classList.add('d-none');
            return true;
        }
    }

    password.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);
    document.forms[0].addEventListener('submit', function(e){
        if (!validatePasswords()) {
            e.preventDefault();
        }
    });
</script>
