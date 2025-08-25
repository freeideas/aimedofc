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
    
    // Get patient's medical records
    $stmt = $db->prepare("
        SELECT record_title, record_type, record_date, content 
        FROM medical_records 
        WHERE user_id = ?
        ORDER BY record_date DESC
    ");
    $stmt->execute([$user_id]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build context for Gemini
    $medical_context = "Medical Records for Patient:\n\n";
    foreach ($records as $record) {
        $medical_context .= "=== {$record['record_title']} ({$record['record_type']}) - {$record['record_date']} ===\n";
        $medical_context .= $record['content'] . "\n\n";
    }
    
    // Get conversation history
    $stmt = $db->prepare("
        SELECT role, message 
        FROM chat_messages 
        WHERE conversation_id = ?
        ORDER BY timestamp ASC
        LIMIT 20
    ");
    $stmt->execute([$conversation_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Call Gemini API
    $creds = json_decode(file_get_contents('../../.creds.json'), true);
    $api_key = $creds['GOOGLE']['API_KEY'];
    
    $gemini_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key=" . $api_key;
    
    $system_prompt = "You are a medical AI assistant. You are NOT a doctor, but you have access to the patient's medical records. 
Your role is to help patients understand their medical records and prepare for doctor appointments. 
Always remind patients that you are not a replacement for professional medical advice.
Be helpful, empathetic, and clear in your explanations.";
    
    // Build conversation history for Gemini
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
                ['text' => $system_prompt . "\n\n" . $medical_context]
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
        throw new Exception('Failed to get response from Gemini API');
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
    
    echo json_encode([
        'success' => true,
        'response' => $ai_response,
        'conversation_id' => $conversation_id,
        'title' => $title ?? null
    ]);
    
} catch (Exception $e) {
    error_log('Chat API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while processing your request'
    ]);
}