<?php
require_once '../infrastructure/lib.php';
$user_id = checkAuth();
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$conversation_id = $_GET['id'] ?? '';

if (empty($conversation_id)) {
    echo json_encode(['success' => false, 'error' => 'Conversation ID is required']);
    exit;
}

$db = getAppDb();

try {
    // Get conversation details
    $stmt = $db->prepare("
        SELECT title, created_at, updated_at 
        FROM conversations 
        WHERE conversation_id = ? AND user_id = ?
    ");
    $stmt->execute([$conversation_id, $user_id]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        echo json_encode(['success' => false, 'error' => 'Conversation not found']);
        exit;
    }
    
    // Get messages (excluding deleted)
    $stmt = $db->prepare("
        SELECT message_id, role, message, timestamp 
        FROM chat_messages 
        WHERE conversation_id = ?
        AND (deleted = 0 OR deleted IS NULL)
        ORDER BY timestamp ASC
    ");
    $stmt->execute([$conversation_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'title' => $conversation['title'],
        'created_at' => $conversation['created_at'],
        'updated_at' => $conversation['updated_at'],
        'messages' => $messages
    ]);
    
} catch (Exception $e) {
    error_log('Get conversation error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while loading the conversation'
    ]);
}