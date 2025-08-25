<?php
require_once '../infrastructure/lib.php';
$user_id = checkAuth();
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';
$conversation_id = $input['conversation_id'] ?? null;
$local_datetime = $input['local_datetime'] ?? '';
$timezone = $input['timezone'] ?? 'UTC';

if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message is required']);
    exit;
}

$db = getAppDb();

try {
    // If no conversation ID, create a new conversation
    if (!$conversation_id) {
        $conversation_id = bin2hex(random_bytes(8));
        $title = mb_substr($message, 0, 50) . (mb_strlen($message) > 50 ? '...' : '');
        
        $stmt = $db->prepare("
            INSERT INTO conversations (conversation_id, user_id, title, created_at, updated_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$conversation_id, $user_id, $title]);
    } else {
        // Update conversation's updated_at
        $stmt = $db->prepare("
            UPDATE conversations 
            SET updated_at = CURRENT_TIMESTAMP 
            WHERE conversation_id = ? AND user_id = ?
        ");
        $stmt->execute([$conversation_id, $user_id]);
    }
    
    // Save user message
    $message_id = bin2hex(random_bytes(8));
    $stmt = $db->prepare("
        INSERT INTO chat_messages (message_id, conversation_id, role, message, timestamp)
        VALUES (?, ?, 'patient', ?, CURRENT_TIMESTAMP)
    ");
    $stmt->execute([$message_id, $conversation_id, $message]);
    
    // Update conversation's updated_at timestamp
    $stmt = $db->prepare("
        UPDATE conversations 
        SET updated_at = CURRENT_TIMESTAMP 
        WHERE conversation_id = ?
    ");
    $stmt->execute([$conversation_id]);
    
    // Get patient's medical records
    $stmt = $db->prepare("
        SELECT record_title, record_type, record_date, content 
        FROM medical_records 
        WHERE user_id = ?
        ORDER BY record_date DESC
    ");
    $stmt->execute([$user_id]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build medical records section
    $medical_records_text = "";
    foreach ($records as $record) {
        $medical_records_text .= "=== {$record['record_title']} ({$record['record_type']}) - Date: {$record['record_date']} ===\n";
        $medical_records_text .= $record['content'] . "\n\n";
    }
    if (empty($medical_records_text)) {
        $medical_records_text = "No medical records available yet.\n";
    }
    
    // Get patient's appointments with notes
    $stmt = $db->prepare("
        SELECT appointment_date, appointment_time, doctor_name, appointment_type, 
               location, notes, status
        FROM appointments 
        WHERE user_id = ?
        ORDER BY appointment_date DESC, appointment_time DESC
    ");
    $stmt->execute([$user_id]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build appointments section
    $appointments_text = "";
    foreach ($appointments as $appt) {
        $appointments_text .= "Date: {$appt['appointment_date']}";
        if ($appt['appointment_time']) {
            $appointments_text .= " at {$appt['appointment_time']}";
        }
        $appointments_text .= "\n";
        $appointments_text .= "Doctor: {$appt['doctor_name']}\n";
        if ($appt['appointment_type']) {
            $appointments_text .= "Type: {$appt['appointment_type']}\n";
        }
        if ($appt['location']) {
            $appointments_text .= "Location: {$appt['location']}\n";
        }
        $appointments_text .= "Status: {$appt['status']}\n";
        if ($appt['notes']) {
            $appointments_text .= "Doctor's Notes:\n{$appt['notes']}\n";
        }
        $appointments_text .= "---\n\n";
    }
    if (empty($appointments_text)) {
        $appointments_text = "No appointment history available.\n";
    }
    
    // Get conversation history (excluding deleted messages)
    $stmt = $db->prepare("
        SELECT role, message 
        FROM chat_messages 
        WHERE conversation_id = ? 
        AND (deleted = 0 OR deleted IS NULL)
        ORDER BY timestamp ASC
    ");
    $stmt->execute([$conversation_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build conversation history text
    $conversation_history_text = "";
    foreach ($history as $msg) {
        if ($msg['role'] === 'patient') {
            $conversation_history_text .= "Patient: {$msg['message']}\n\n";
        } else {
            $conversation_history_text .= "Assistant: {$msg['message']}\n\n";
        }
    }
    
    // Load prompt template
    $template = file_get_contents(__DIR__ . '/prompt_template.txt');
    
    // Get current UTC datetime
    $utc_datetime = gmdate('Y-m-d H:i:s') . ' UTC';
    
    // Replace template variables
    $system_prompt = str_replace(
        ['{{UTC_DATETIME}}', '{{LOCAL_DATETIME}}', '{{TIMEZONE}}', '{{MEDICAL_RECORDS}}', '{{APPOINTMENTS}}', '{{CONVERSATION_HISTORY}}'],
        [$utc_datetime, $local_datetime ?: 'Not provided', $timezone, $medical_records_text, $appointments_text, $conversation_history_text],
        $template
    );
    
    // Call Gemini API
    $config = loadCreds();
    $api_key = $config['gemini_api_key'];
    
    if (empty($api_key)) {
        error_log('Gemini API key is empty or not configured');
        throw new Exception('API key not configured');
    }
    
    $gemini_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key=" . $api_key;
    
    // Build conversation history for Gemini (including current message)
    $conversation_parts = [];
    foreach ($history as $msg) {
        if ($msg['role'] === 'patient') {
            $conversation_parts[] = [
                'role' => 'user',
                'parts' => [['text' => $msg['message']]]
            ];
        } else {
            $conversation_parts[] = [
                'role' => 'model',
                'parts' => [['text' => $msg['message']]]
            ];
        }
    }
    
    $gemini_request = [
        'contents' => $conversation_parts,
        'systemInstruction' => [
            'parts' => [
                ['text' => $system_prompt]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 1000
        ]
    ];
    
    $ch = curl_init($gemini_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($gemini_request));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $gemini_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        error_log('Gemini API HTTP code: ' . $http_code);
        error_log('Gemini API response: ' . $gemini_response);
        throw new Exception('Failed to get response from Gemini API: HTTP ' . $http_code);
    }
    
    $gemini_data = json_decode($gemini_response, true);
    $ai_response = $gemini_data['candidates'][0]['content']['parts'][0]['text'] ?? 'I apologize, but I was unable to generate a response. Please try again.';
    
    // Save AI response
    $response_id = bin2hex(random_bytes(8));
    $stmt = $db->prepare("
        INSERT INTO chat_messages (message_id, conversation_id, role, message, timestamp)
        VALUES (?, ?, 'assistant', ?, CURRENT_TIMESTAMP)
    ");
    $stmt->execute([$response_id, $conversation_id, $ai_response]);
    
    // Update conversation's updated_at timestamp again for the response
    $stmt = $db->prepare("
        UPDATE conversations 
        SET updated_at = CURRENT_TIMESTAMP 
        WHERE conversation_id = ?
    ");
    $stmt->execute([$conversation_id]);
    
    echo json_encode([
        'success' => true,
        'response' => $ai_response,
        'conversation_id' => $conversation_id,
        'title' => $title ?? null,
        'user_message_id' => $message_id,
        'ai_message_id' => $response_id
    ]);
    
} catch (Exception $e) {
    error_log('Chat API error: ' . $e->getMessage());
    error_log('Chat API trace: ' . $e->getTraceAsString());
    
    // For debugging, include error in response (remove in production)
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while processing your request',
        'debug' => $e->getMessage()
    ]);
}