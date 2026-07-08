<?php
header('Content-Type: text/plain');

$logFile = __DIR__ . '/../storage/logs/laravel.log';

if (!file_exists($logFile)) {
    echo "Log file does not exist at: " . $logFile;
    exit;
}

$search = isset($_GET['q']) ? $_GET['q'] : 'draft_orders';
$lines = file($logFile);
$matches = [];

foreach ($lines as $line) {
    if (stripos($line, $search) !== false) {
        $matches[] = $line;
    }
}

echo "=== MATCHING LOG LINES FOR '{$search}' ===\n\n";
echo implode("", array_slice($matches, -100)); // Show last 100 matches
