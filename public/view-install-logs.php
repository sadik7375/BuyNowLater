<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain');

$logFile = storage_path('logs/laravel.log');
if (!file_exists($logFile)) {
    echo "Log file not found.\n";
    exit;
}

$lines = file($logFile);
$installLogs = [];

foreach ($lines as $line) {
    if (str_contains($line, 'CustomInstallShop') || str_contains($line, 'CustomAuthenticateShop') || str_contains($line, 'AuthenticateShop') || str_contains($line, 'auth.proxy')) {
        $installLogs[] = $line;
    }
}

echo "Found " . count($installLogs) . " log entries related to auth/installation.\n";
echo "Showing last 100 entries:\n\n";

$lastEntries = array_slice($installLogs, -100);
foreach ($lastEntries as $entry) {
    echo $entry;
}
