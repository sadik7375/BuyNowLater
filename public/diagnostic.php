<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "<h1>Diagnostic Info</h1>";

// 1. Check database connection and users table schema
try {
    $columns = Schema::getColumnListing('users');
    echo "<h3>Database Connection: SUCCESS</h3>";
    echo "<h4>Users Table Columns:</h4>";
    echo "<pre>" . print_r($columns, true) . "</pre>";
} catch (\Exception $e) {
    echo "<h3>Database Connection: FAILED</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 2. Read Laravel Log files for detailed errors
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    echo "<h3>Last 100 lines of Laravel Log:</h3>";
    $lines = file($logFile);
    $lastLines = array_slice($lines, -100);
    echo "<pre style='background: #f4f4f4; padding: 10px; border: 1px solid #ccc; max-height: 400px; overflow-y: scroll;'>";
    foreach ($lastLines as $line) {
        echo htmlspecialchars($line);
    }
    echo "</pre>";
} else {
    echo "<h3>Laravel Log file not found!</h3>";
}
