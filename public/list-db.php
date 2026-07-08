<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Reminder;

header('Content-Type: text/plain');
echo "=== DATABASE REMINDERS LIST ===\n\n";

$reminders = Reminder::orderBy('id', 'desc')->get();
foreach ($reminders as $r) {
    echo "ID: {$r->id}\n";
    echo "Product: {$r->product_title}\n";
    echo "Email: {$r->email}\n";
    echo "Scheduled At: {$r->scheduled_at} (UTC)\n";
    echo "Status: {$r->status}\n";
    echo "Sent At: {$r->sent_at}\n";
    echo "Created At: {$r->created_at}\n";
    echo "---------------------------\n";
}
