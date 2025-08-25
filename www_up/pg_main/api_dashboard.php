<?php
declare(strict_types=1);

require_once '../infrastructure/lib.php';

header('Content-Type: application/json');

// Get token from cookie
$token = $_COOKIE['aiofc_session'] ?? '';

// Use refresh=true to extend session to 32 days from now
$userId = getUserIdFromToken($token, true);
if (!$userId) {
    http_response_code(401);
    die(json_encode(['error' => 'Not authenticated']));
}

try {
    // Connect to application database
    $dbPath = __DIR__ . '/../../data/aioffice.db';
    $db = new SQLite3($dbPath);
    $db->busyTimeout(5000);
    
    // Attach auth database to get user email
    $authDbPath = __DIR__ . '/../../data/auth.db';
    $db->exec("ATTACH DATABASE '$authDbPath' AS auth");
    
    // Get user information
    $stmt = $db->prepare("
        SELECT 
            auth.users.email,
            patients.full_name
        FROM auth.users
        LEFT JOIN patients ON auth.users.id = patients.user_id
        WHERE auth.users.id = :user_id
    ");
    $stmt->bindValue(':user_id', $userId, SQLITE3_TEXT);
    $result = $stmt->execute();
    $userRow = $result->fetchArray(SQLITE3_ASSOC);
    
    $userData = [
        'name' => $userRow['full_name'] ?? 'Patient',
        'email' => $userRow['email'] ?? ''
    ];
    
    // Get recent conversations (last 3)
    $stmt = $db->prepare("
        SELECT 
            conversation_id,
            title as preview,
            updated_at as timestamp
        FROM conversations
        WHERE user_id = :user_id
        ORDER BY updated_at DESC
        LIMIT 3
    ");
    $stmt->bindValue(':user_id', $userId, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    $recentChats = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $recentChats[] = [
            'id' => $row['conversation_id'],
            'preview' => substr($row['preview'], 0, 100) . '...',
            'timestamp' => $row['timestamp']
        ];
    }
    
    // Check if user has medical records
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM medical_records
        WHERE user_id = :user_id
    ");
    $stmt->bindValue(':user_id', $userId, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $hasMedicalRecords = $row['count'] > 0;
    
    // Get latest appointment (even if in the past)
    $stmt = $db->prepare("
        SELECT 
            appointment_date as date,
            appointment_time as time,
            appointment_datetime_utc,
            doctor_name,
            appointment_type,
            location
        FROM appointments
        WHERE user_id = :user_id
        ORDER BY appointment_datetime_utc DESC, appointment_date DESC
        LIMIT 1
    ");
    $stmt->bindValue(':user_id', $userId, SQLITE3_TEXT);
    $result = $stmt->execute();
    $nextAppointment = $result->fetchArray(SQLITE3_ASSOC);
    
    // If we have a UTC datetime, use that; otherwise use date and time fields
    if ($nextAppointment && $nextAppointment['appointment_datetime_utc']) {
        $utcDateTime = new DateTime($nextAppointment['appointment_datetime_utc'], new DateTimeZone('UTC'));
        $nextAppointment['date'] = $utcDateTime->format('Y-m-d');
        $nextAppointment['time'] = $utcDateTime->format('H:i');
    }
    
    $db->close();
    
    // Return dashboard data
    echo json_encode([
        'user' => $userData,
        'next_appointment' => $nextAppointment ?: null,
        'recent_chats' => $recentChats,
        'has_medical_records' => $hasMedicalRecords
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}