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
$message_id = $input['message_id'] ?? '';
$conversation_id = $input['conversation_id'] ?? '';
$action = $input['action'] ?? 'delete'; // 'delete' or 'clear'

if ($action === 'clear' && empty($conversation_id)) {
    echo json_encode(['success' => false, 'error' => 'Conversation ID required for clear action']);
    exit;
}

if ($action === 'delete' && empty($message_id)) {
    echo json_encode(['success' => false, 'error' => 'Message ID required for delete action']);
    exit;
}

$db = getAppDb();

try {
    // First, ensure the deleted column exists
    $stmt = $db->query("PRAGMA table_info(chat_messages)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasDeletedColumn = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'deleted') {
            $hasDeletedColumn = true;
            break;
        }
    }
    
    if (!$hasDeletedColumn) {
        // Add the deleted column if it doesn't exist
        $db->exec("ALTER TABLE chat_messages ADD COLUMN deleted INTEGER DEFAULT 0");
    }
    
    if ($action === 'clear') {
        // Verify conversation belongs to user and mark all messages as deleted
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM conversations 
            WHERE conversation_id = ? AND user_id = ?
        ");
        $stmt->execute([$conversation_id, $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            echo json_encode(['success' => false, 'error' => 'Conversation not found']);
            exit;
        }
        
        // Mark all messages in conversation as deleted
        $stmt = $db->prepare("
            UPDATE chat_messages 
            SET deleted = 1 
            WHERE conversation_id = ?
        ");
        $stmt->execute([$conversation_id]);
        
        echo json_encode(['success' => true, 'action' => 'cleared']);
    } else {
        // Delete single message - verify it belongs to user's conversation
        $stmt = $db->prepare("
            SELECT c.user_id 
            FROM chat_messages m
            JOIN conversations c ON m.conversation_id = c.conversation_id
            WHERE m.message_id = ? AND c.user_id = ?
        ");
        $stmt->execute([$message_id, $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            echo json_encode(['success' => false, 'error' => 'Message not found']);
            exit;
        }
        
        // Mark message as deleted
        $stmt = $db->prepare("
            UPDATE chat_messages 
            SET deleted = 1 
            WHERE message_id = ?
        ");
        $stmt->execute([$message_id]);
        
        echo json_encode(['success' => true, 'action' => 'deleted']);
    }
    
} catch (Exception $e) {
    error_log('Delete message API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while processing your request'
    ]);
}