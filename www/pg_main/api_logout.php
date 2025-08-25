<?php
declare(strict_types=1);

require_once '../infrastructure/lib.php';

header('Content-Type: application/json');

// Get token from cookie
$token = $_COOKIE['aiofc_session'] ?? '';

if ($token) {
    // Invalidate session in database
    $authDbPath = __DIR__ . '/../../data/auth.db';
    $db = new SQLite3($authDbPath);
    $db->busyTimeout(5000);
    
    // Delete session from database
    $stmt = $db->prepare("DELETE FROM sessions WHERE token = :token");
    $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    $stmt->execute();
    
    $db->close();
}

// Clear session cookie
setcookie('aiofc_session', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Strict'
]);

echo json_encode(['success' => true]);