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
$conversation_id = $input['conversation_id'] ?? '';

if (empty($conversation_id)) {
    echo json_encode(['success' => false, 'error' => 'Conversation ID required']);
    exit;
}

$db = getAppDb();

try {
    // First verify the conversation belongs to this user
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
    
    // Soft delete the conversation by setting deleted_flag = 1
    $stmt = $db->prepare("
        UPDATE conversations 
        SET deleted_flag = 1,
            updated_at = CURRENT_TIMESTAMP
        WHERE conversation_id = ? AND user_id = ?
    ");
    $stmt->execute([$conversation_id, $user_id]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log('Delete conversation API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while deleting the conversation'
    ]);
}