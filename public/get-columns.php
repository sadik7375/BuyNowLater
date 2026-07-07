<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

header('Content-Type: text/plain');

$tables = ['users', 'buylater_settings', 'bookings'];
foreach ($tables as $table) {
    echo "Columns in '$table':\n";
    if (Schema::hasTable($table)) {
        $columns = Schema::getColumnListing($table);
        foreach ($columns as $column) {
            echo " - $column\n";
        }
    } else {
        echo " [Table does not exist]\n";
    }
    echo "\n";
}
