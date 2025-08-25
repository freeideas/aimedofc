<?php
declare(strict_types=1);

require_once 'infrastructure/lib.php';

// Check if user is already logged in
$token = $_COOKIE['aiofc_session'] ?? '';
if ($token) {
    $userId = getUserIdFromToken($token, true); // Refresh session
    if ($userId) {
        // User is already logged in, redirect to main dashboard
        header('Location: pg_main/');
        exit;
    }
}

// Otherwise show the landing page in an iframe
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Office - Medical Assistant Application</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
        }
        iframe {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
        }
    </style>
</head>
<body>
    <iframe src="pg_index/" title="AI Office"></iframe>
</body>
</html>