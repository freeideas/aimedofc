<?php
require_once '../www_up/infrastructure/include.php';

// Check if requesting a static asset from pg_index
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (preg_match('/\/(style\.css|app\.js)$/', $requestUri)) {
    // Extract just the filename
    $file = basename($requestUri);
    $filePath = __DIR__ . '/../www_up/pg_index/' . $file;
    
    if (file_exists($filePath)) {
        // Set appropriate content type
        if (substr($file, -3) === '.js') {
            header('Content-Type: application/javascript');
        } elseif (substr($file, -4) === '.css') {
            header('Content-Type: text/css');
        }
        readfile($filePath);
        exit;
    }
}

// Otherwise, serve the pg_index HTML content
$indexPath = __DIR__ . '/../www_up/pg_index/index.html';
if (!file_exists($indexPath)) {
    // Fallback if www_up structure changes
    die('<!DOCTYPE html><html><body><h1>Site Under Maintenance</h1><p>We\'ll be back shortly.</p></body></html>');
}

// Just serve the content as-is - links will naturally be broken
echo file_get_contents($indexPath);
?>