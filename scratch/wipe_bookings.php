<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $count = App\Models\Booking::count();
    App\Models\Booking::truncate();
    echo "Successfully deleted {$count} booking records from database.\n";
} catch (\Exception $e) {
    echo "Error truncating bookings: " . $e->getMessage() . "\n";
}
