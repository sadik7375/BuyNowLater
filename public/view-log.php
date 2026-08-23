<?php
$baseDir = dirname(__DIR__);
chdir($baseDir);

if (file_exists($baseDir . '/.git/index.lock')) {
    @unlink($baseDir . '/.git/index.lock');
}
if (file_exists($baseDir . '/public/hot')) {
    @unlink($baseDir . '/public/hot');
}

echo "<h2>Executing Git Pull to latest main...</h2><pre>";
echo shell_exec("git remote set-url origin https://github.com/sadik7375/BuyNowLater.git 2>&1") . "\n";
echo shell_exec("git fetch origin main --force 2>&1") . "\n";
echo shell_exec("git reset --hard origin/main 2>&1") . "\n";
echo shell_exec("php artisan optimize:clear 2>&1") . "\n";
echo "</pre>";

$logFile = dirname(__DIR__) . '/storage/logs/laravel.log';
echo "<h1>Laravel Log (Last 100 lines)</h1><pre>";
if (file_exists($logFile)) {
    $lines = file($logFile);
    $lastLines = array_slice($lines, -100);
    echo htmlspecialchars(implode("", $lastLines));
} else {
    echo "Log file does not exist: " . $logFile;
}
echo "</pre>";
