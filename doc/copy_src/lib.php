<?php
declare(strict_types=1);


/**
 * Generate a base62-encoded UUID
 */
function generateID(): string {
    // Generate UUID v4 (128 bits)
    $uuid = random_bytes(16);
    // Convert to base62 (0-9, a-z, A-Z)
    $base62 = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $num = gmp_import($uuid);
    $result = '';    
    while (gmp_cmp($num, 0) > 0) {
        $remainder = gmp_mod($num, 62);
        $result = $base62[gmp_intval($remainder)] . $result;
        $num = gmp_div($num, 62);
    }    
    return str_pad($result, 22, '0', STR_PAD_LEFT);  // Ensure 22 chars
}


/**
 * Generate a 6-digit verification code
 */
function generateVerificationCode(): string {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}


/**
 * Validate email address format
 */
function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}


/**
 * Sanitize user input to prevent XSS
 */
function sanitizeInput(string $input): string {
    return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}


/**
 * Validate session token and return user ID
 * @param string $token The session token to validate
 * @param bool $refresh Whether to refresh the session expiry (adds 32 days)
 */
function getUserIdFromToken(string $token, bool $refresh = false): ?string {
    if (empty($token)) return null;
    
    $authDbPath = __DIR__ . '/../../data/auth.db';
    if (!file_exists($authDbPath)) return null;
    
    try {
        $db = new SQLite3($authDbPath);
        $db->busyTimeout(5000);
        
        // Check if token exists and is not expired
        $stmt = $db->prepare("
            SELECT user_id 
            FROM sessions 
            WHERE token = :token 
            AND expires_at > datetime('now')
            LIMIT 1
        ");
        $stmt->bindValue(':token', $token, SQLITE3_TEXT);
        $result = $stmt->execute();
        
        if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $userId = $row['user_id'];
            
            if ($refresh) {
                // Refresh session expiry to 32 days from now
                $updateStmt = $db->prepare("
                    UPDATE sessions 
                    SET last_activity = datetime('now'),
                        expires_at = datetime('now', '+32 days')
                    WHERE token = :token
                ");
                $updateStmt->bindValue(':token', $token, SQLITE3_TEXT);
                $updateStmt->execute();
            } else {
                // Just update last_activity timestamp
                $updateStmt = $db->prepare("
                    UPDATE sessions 
                    SET last_activity = datetime('now') 
                    WHERE token = :token
                ");
                $updateStmt->bindValue(':token', $token, SQLITE3_TEXT);
                $updateStmt->execute();
            }
            
            $db->close();
            return $userId;
        }
        
        $db->close();
    } catch (Exception $e) {
        // Log error if needed, return null for invalid session
    }
    
    return null;
}


/**
 * Load credentials from external JSON file
 */
function loadCreds(): array {
    $credsPath = __DIR__ . '/../../../.creds.json';
    $creds = file_exists($credsPath) ? json_decode(file_get_contents($credsPath), true) : [];
    return [
        'db_path' => __DIR__ . '/../../data/aioffice.db',
        'gemini_api_key' => $creds['GOOGLE']['API_KEY'] ?? '',
        'email_smtp_host' => $creds['smtp_host'] ?? '',
        'email_smtp_user' => $creds['smtp_user'] ?? '',
        'email_smtp_pass' => $creds['smtp_pass'] ?? '',
        'email_from' => 'noreply@aioffice.com'
    ];
}