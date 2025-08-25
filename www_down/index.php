<?php
// Load the pg_index content
$indexPath = __DIR__ . '/../www_up/pg_index/index.html';
if (!file_exists($indexPath)) {
    // Fallback if www_up structure changes
    die('<!DOCTYPE html><html><body><h1>Site Under Maintenance</h1><p>We\'ll be back shortly.</p></body></html>');
}

$content = file_get_contents($indexPath);

// Add maintenance banner styles
$maintenanceStyles = '
<style>
.maintenance-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
    color: white;
    padding: 15px;
    text-align: center;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    font-size: 18px;
    font-weight: 600;
    z-index: 10000;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}
.maintenance-icon {
    display: inline-block;
    margin-right: 10px;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
body {
    padding-top: 60px !important; /* Make room for banner */
}
/* Disable all interactive elements */
a, button {
    pointer-events: none !important;
    opacity: 0.6 !important;
    cursor: not-allowed !important;
}
/* Add overlay effect to indicate non-interactive state */
.hero::after, .features::after, .cta::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255,255,255,0.1);
    pointer-events: none;
}
.hero, .features, .cta {
    position: relative;
}
</style>
';

// Add maintenance banner HTML
$maintenanceBanner = '
<div class="maintenance-banner">
    <span class="maintenance-icon">🔧</span>
    Site Under Maintenance - We\'ll be back shortly!
</div>
';

// Insert styles before </head>
$content = str_replace('</head>', $maintenanceStyles . '</head>', $content);

// Insert banner after <body>
$content = str_replace('<body>', '<body>' . $maintenanceBanner, $content);

// Disable all links by replacing href attributes
$content = preg_replace('/href="([^"]*)"/', 'href="#" data-original-href="$1"', $content);

// Disable form submissions
$content = str_replace('<form', '<form onsubmit="return false;"', $content);

// Update button text to indicate disabled state
$content = str_replace('Get Started', 'Currently Unavailable', $content);
$content = str_replace('Get Started Now', 'Currently Unavailable', $content);

// Output the modified content
echo $content;
?>