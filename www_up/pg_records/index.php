<?php
declare(strict_types=1);
require_once '../infrastructure/lib.php';
require_once '../infrastructure/include.php';

// Check authentication
$userId = checkAuth();
if (!$userId) {
    header('Location: /pg_login/');
    exit;
}

// Get user's medical records
$db = getAppDb();
$stmt = $db->prepare("
    SELECT record_id, record_title, record_type, record_date, source_filename
    FROM medical_records
    WHERE user_id = :user_id
    ORDER BY record_date DESC, created_at DESC
");
$stmt->execute(['user_id' => $userId]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected record ID (if any)
$selectedId = $_GET['id'] ?? ($records[0]['record_id'] ?? null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records - Medical Office Assistant</title>
    <link rel="stylesheet" href="../infrastructure/glassmorphic.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <h1>Medical Records</h1>
        <a href="../pg_main/" class="back-link">Back to Dashboard</a>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <?php if (empty($records)): ?>
                <div class="no-records">
                    <p>No medical records available</p>
                </div>
            <?php else: ?>
                <?php foreach ($records as $record): ?>
                    <a href="?id=<?= htmlspecialchars($record['record_id']) ?>" 
                       class="record-item <?= $selectedId === $record['record_id'] ? 'active' : '' ?>">
                        <div class="record-title"><?= htmlspecialchars($record['record_title'] ?: 'Untitled Document') ?></div>
                        <div class="record-date"><?= htmlspecialchars($record['record_date'] ?: 'No date') ?></div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="content">
            <div class="pdf-container">
                <?php if ($selectedId && !empty($records)): ?>
                    <iframe src="get_pdf.php?id=<?= htmlspecialchars($selectedId) ?>" 
                            class="pdf-frame"
                            title="PDF Viewer"></iframe>
                <?php else: ?>
                    <div class="no-selection">
                        <p>Select a document to view</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>