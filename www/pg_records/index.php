<?php
declare(strict_types=1);
require_once '../infrastructure/lib.php';

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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: #f5f5f5;
        }
        
        .header {
            background: white;
            border-bottom: 1px solid #ddd;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .header h1 {
            font-size: 1.5rem;
            color: #333;
        }
        
        .back-link {
            color: #007bff;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border: 1px solid #007bff;
            border-radius: 4px;
            transition: background 0.2s;
        }
        
        .back-link:hover {
            background: #007bff;
            color: white;
        }
        
        .container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        
        .sidebar {
            width: 300px;
            background: white;
            border-right: 1px solid #ddd;
            overflow-y: auto;
        }
        
        .record-item {
            display: block;
            padding: 1rem;
            border-bottom: 1px solid #eee;
            text-decoration: none;
            color: #333;
            transition: background 0.2s;
            cursor: pointer;
        }
        
        .record-item:hover {
            background: #f8f9fa;
        }
        
        .record-item.active {
            background: #e3f2fd;
            border-left: 3px solid #2196f3;
        }
        
        .record-title {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .record-date {
            font-size: 0.875rem;
            color: #666;
        }
        
        .content {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f5f5f5;
        }
        
        .pdf-container {
            flex: 1;
            padding: 1rem;
        }
        
        .pdf-frame {
            width: 100%;
            height: 100%;
            border: none;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .no-records {
            padding: 2rem;
            text-align: center;
            color: #666;
        }
        
        .no-selection {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                max-height: 40vh;
                border-right: none;
                border-bottom: 1px solid #ddd;
            }
        }
    </style>
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