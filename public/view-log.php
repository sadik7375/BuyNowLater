<?php
$logFile = dirname(__DIR__) . '/storage/logs/laravel.log';
echo "<h1>Laravel Log (Last 100 lines)</h1><pre>";
if (file_exists($logFile)) {
    $lines = file($logFile);
    $lastLines = array_slice($lines, -150);
    echo htmlspecialchars(implode("", $lastLines));
} else {
    echo "Log file does not exist: " . $logFile;
}
echo "</pre>";
