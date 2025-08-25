<?php
declare(strict_types=1);
require_once '../infrastructure/lib.php';

// Check authentication
$userId = checkAuth();
if (!$userId) {
    http_response_code(403);
    exit('Unauthorized');
}

// Get record ID
$recordId = $_GET['id'] ?? '';
if (!$recordId) {
    http_response_code(400);
    exit('Missing record ID');
}

// Get record from database
$db = getAppDb();
$stmt = $db->prepare("
    SELECT source_filename 
    FROM medical_records 
    WHERE record_id = :record_id AND user_id = :user_id
");
$stmt->execute([
    'record_id' => $recordId,
    'user_id' => $userId
]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record || !$record['source_filename']) {
    http_response_code(404);
    exit('Record not found');
}

// Construct safe file path
$filename = basename($record['source_filename']); // Prevent path traversal
$filepath = realpath(__DIR__ . '/../../data/uploads/' . $filename);

// Verify file exists and is within uploads directory
$uploadsDir = realpath(__DIR__ . '/../../data/uploads');
if (!$filepath || !file_exists($filepath) || strpos($filepath, $uploadsDir) !== 0) {
    http_response_code(404);
    exit('File not found');
}

// Serve the PDF
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=3600');

readfile($filepath);