<?php
header('Content-Type: text/plain');

$logFile = __DIR__ . '/../storage/logs/laravel.log';

if (!file_exists($logFile)) {
    echo "Log file does not exist.";
    exit;
}

$contents = file_get_contents($logFile);
$lines = explode("\n", $contents);

echo "=== WEBHOOK & ORDER LOGS ===\n\n";

$found = false;
foreach ($lines as $line) {
    if (strpos($line, 'OrdersPaidJob') !== false || strpos($line, 'webhook') !== false || strpos($line, 'Error') !== false || strpos($line, 'Exception') !== false) {
        echo $line . "\n";
        $found = true;
    }
}

if (!$found) {
    echo "No matching log entries found.";
}
