<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../infrastructure/lib.php';

$input = json_decode(file_get_contents('php://input'), true);

$authDbPath = __DIR__ . '/../../data/auth.db';

// Initialize database if it doesn't exist
if (!file_exists($authDbPath)) {
    $db = new SQLite3($authDbPath);
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id TEXT PRIMARY KEY,
            email TEXT UNIQUE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME
        );
        
        CREATE TABLE IF NOT EXISTS sessions (
            id TEXT PRIMARY KEY,
            user_id TEXT NOT NULL,
            token TEXT UNIQUE NOT NULL,
            device_info TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
        
        CREATE TABLE IF NOT EXISTS verification_codes (
            id TEXT PRIMARY KEY,
            email TEXT NOT NULL,
            code TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            used BOOLEAN DEFAULT 0
        );
    ");
    $db->close();
}

// Handle token validation (auto-login check)
if (isset($input['token']) && !isset($input['email'])) {
    $token = $input['token'];
    $userId = getUserIdFromToken($token, true); // Refresh expiry
    
    if ($userId) {
        // Refresh cookie expiry to match database
        setcookie('aiofc_session', $token, [
            'expires' => time() + (32 * 24 * 60 * 60),
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        
        echo json_encode(['success' => true, 'redirect' => '../pg_main/']);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired session']);
    }
    exit;
}

// Handle email submission (send verification code)
if (isset($input['email']) && !isset($input['code'])) {
    $email = trim($input['email']);
    
    if (!isValidEmail($email)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email format']);
        exit;
    }
    
    $db = new SQLite3($authDbPath);
    $db->busyTimeout(5000);
    
    // Generate and store verification code
    $code = generateVerificationCode();
    $codeId = generateID();
    
    $stmt = $db->prepare("
        INSERT INTO verification_codes (id, email, code, expires_at)
        VALUES (:id, :email, :code, datetime('now', '+15 minutes'))
    ");
    $stmt->bindValue(':id', $codeId, SQLITE3_TEXT);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->bindValue(':code', $code, SQLITE3_TEXT);
    $stmt->execute();
    
    $db->close();
    
    // Send email with verification code
    $subject = "Medical Office Assistant - Verification Code";
    $body = "Your verification code is: $code\n\nThis code will expire in 15 minutes.";
    
    // Use the email-send command with error capture
    $emailCmd = "/home/ace/bin/email-send " . escapeshellarg($email) . " " . 
                escapeshellarg($subject) . " " . escapeshellarg($body) . " 2>&1";
    $output = [];
    $returnCode = 0;
    exec($emailCmd, $output, $returnCode);
    
    // Log any errors for debugging
    if ($returnCode !== 0) {
        error_log("Email send failed: " . implode("\n", $output));
    }
    
    echo json_encode(['success' => true, 'message' => 'Verification code sent']);
    exit;
}

// Handle code verification (complete login)
if (isset($input['email']) && isset($input['code'])) {
    $email = trim($input['email']);
    $code = trim($input['code']);
    
    if (!isValidEmail($email)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email format']);
        exit;
    }
    
    $db = new SQLite3($authDbPath);
    $db->busyTimeout(5000);
    
    // Verify code
    $stmt = $db->prepare("
        SELECT id FROM verification_codes
        WHERE email = :email 
        AND code = :code 
        AND used = 0
        AND expires_at > datetime('now')
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->bindValue(':code', $code, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    if (!$result->fetchArray()) {
        $db->close();
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired verification code']);
        exit;
    }
    
    // Mark code as used
    $stmt = $db->prepare("
        UPDATE verification_codes 
        SET used = 1 
        WHERE email = :email AND code = :code
    ");
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->bindValue(':code', $code, SQLITE3_TEXT);
    $stmt->execute();
    
    // Get or create user
    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$user) {
        // Create new user
        $userId = generateID();
        $stmt = $db->prepare("
            INSERT INTO users (id, email, last_login)
            VALUES (:id, :email, datetime('now'))
        ");
        $stmt->bindValue(':id', $userId, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->execute();
    } else {
        $userId = $user['id'];
        // Update last login
        $stmt = $db->prepare("
            UPDATE users SET last_login = datetime('now') WHERE id = :id
        ");
        $stmt->bindValue(':id', $userId, SQLITE3_TEXT);
        $stmt->execute();
    }
    
    // Create session
    $sessionId = generateID();
    $sessionToken = generateID();
    $deviceInfo = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $stmt = $db->prepare("
        INSERT INTO sessions (id, user_id, token, device_info, expires_at)
        VALUES (:id, :user_id, :token, :device_info, datetime('now', '+32 days'))
    ");
    $stmt->bindValue(':id', $sessionId, SQLITE3_TEXT);
    $stmt->bindValue(':user_id', $userId, SQLITE3_TEXT);
    $stmt->bindValue(':token', $sessionToken, SQLITE3_TEXT);
    $stmt->bindValue(':device_info', $deviceInfo, SQLITE3_TEXT);
    $stmt->execute();
    
    $db->close();
    
    // For test user, ensure test data exists in application database
    if ($email === 'ai@ironmedia.com') {
        try {
            $appDbPath = __DIR__ . '/../../data/aioffice.db';
            $appDb = new SQLite3($appDbPath);
            $appDb->busyTimeout(5000);
            
            // Create tables if they don't exist (matching existing schema)
            $appDb->exec("
                CREATE TABLE IF NOT EXISTS patients (
                    user_id TEXT PRIMARY KEY,
                    full_name TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            $appDb->exec("
                CREATE TABLE IF NOT EXISTS appointments (
                    appointment_id TEXT PRIMARY KEY,
                    user_id TEXT NOT NULL,
                    doctor_name TEXT NOT NULL,
                    appointment_date DATE NOT NULL,
                    appointment_time TIME,
                    appointment_datetime_utc DATETIME,
                    appointment_type TEXT,
                    location TEXT,
                    notes TEXT,
                    status TEXT DEFAULT 'scheduled',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES patients(user_id)
                )
            ");
            
            // Insert or update patient record with test name
            $stmt = $appDb->prepare("
                INSERT OR REPLACE INTO patients (user_id, full_name, updated_at)
                VALUES (:user_id, 'John Doe', datetime('now'))
            ");
            $stmt->bindValue(':user_id', $userId, SQLITE3_TEXT);
            $stmt->execute();
            
            // Check if test appointment already exists
            $stmt = $appDb->prepare("
                SELECT appointment_id FROM appointments 
                WHERE user_id = :user_id 
                AND doctor_name = 'Dr. Smith'
            ");
            $stmt->bindValue(':user_id', $userId, SQLITE3_TEXT);
            $result = $stmt->execute();
            
            if (!$result->fetchArray()) {
                // Insert test appointment (future date)
                $appointmentId = generateID();
                $futureDate = date('Y-m-d', strtotime('+7 days'));
                $futureDateTime = $futureDate . ' 14:00:00'; // 2 PM UTC
                
                $stmt = $appDb->prepare("
                    INSERT INTO appointments (
                        appointment_id, user_id, doctor_name, appointment_date, 
                        appointment_time, appointment_datetime_utc, 
                        appointment_type, location, status
                    ) VALUES (
                        :id, :user_id, 'Dr. Smith', :date,
                        '14:00', :datetime_utc,
                        'Annual Physical', 'Main Clinic, Room 203', 'scheduled'
                    )
                ");
                $stmt->bindValue(':id', $appointmentId, SQLITE3_TEXT);
                $stmt->bindValue(':user_id', $userId, SQLITE3_TEXT);
                $stmt->bindValue(':date', $futureDate, SQLITE3_TEXT);
                $stmt->bindValue(':datetime_utc', $futureDateTime, SQLITE3_TEXT);
                $stmt->execute();
            }
            
            $appDb->close();
        } catch (Exception $e) {
            // Log error but don't fail login for test user
            error_log("Test data insertion error: " . $e->getMessage());
        }
    }
    
    // Set session cookie
    setcookie('aiofc_session', $sessionToken, [
        'expires' => time() + (32 * 24 * 60 * 60),
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    echo json_encode(['success' => true, 'redirect' => '../pg_main/']);
    exit;
}

// Invalid request
http_response_code(400);
echo json_encode(['error' => 'Invalid request']);