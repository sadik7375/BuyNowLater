<?php
$logFile = __DIR__ . '/../storage/logs/laravel.log';
if (!file_exists($logFile)) {
    echo "Log file does not exist: $logFile";
    exit;
}

$lines = file($logFile);
$lastLines = array_slice($lines, -150);

echo "<pre>";
foreach ($lastLines as $line) {
    echo htmlspecialchars($line);
}
echo "</pre>";
