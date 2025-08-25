<?php
declare(strict_types=1);

require_once '../infrastructure/lib.php';
require_once '../infrastructure/include.php';

// Check if user is already logged in
$token = $_COOKIE['aiofc_session'] ?? '';
if ($token) {
    $userId = getUserIdFromToken($token, true); // Refresh session
    if ($userId) {
        // User is already logged in, redirect to main dashboard
        header('Location: ../pg_main/');
        exit;
    }
}

// Otherwise show the login page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Medical Office Assistant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="login-box">
            <h1>Medical Office Assistant</h1>
            <p class="subtitle">Medical consultation preparation assistant</p>
            
            <form id="loginForm">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email">
                </div>
                
                <div class="form-group" id="codeGroup" style="display: none;">
                    <label for="code">Verification Code</label>
                    <input type="text" id="code" name="code" maxlength="6" pattern="[0-9]{6}" placeholder="Enter 6-digit code">
                    <p class="help-text">Check your email for the verification code</p>
                </div>
                
                <button type="submit" id="submitBtn">Send Verification Code</button>
                
                <div id="message" class="message"></div>
            </form>
            
            <div class="footer">
                <p>Secure passwordless authentication</p>
                <p class="small">By continuing, you agree to our terms and privacy policy</p>
            </div>
        </div>
    </div>
    
    <script src="app.js"></script>
</body>
</html>