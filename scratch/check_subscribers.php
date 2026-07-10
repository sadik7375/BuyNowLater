<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Illuminate\Contracts\Console\Kernel;
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$subscribers = \App\Models\Subscriber::all();
echo "Total subscribers: " . $subscribers->count() . "\n";
foreach ($subscribers as $sub) {
    echo "ID: {$sub->id} | Email: {$sub->email} | Prod ID: {$sub->product_id} | Title: {$sub->product_title} | Price: {$sub->product_price} | Status: {$sub->status} | Notified: {$sub->notified_at}\n";
}

$reminders = \App\Models\Reminder::all();
echo "\nTotal reminders: " . $reminders->count() . "\n";
foreach ($reminders as $rem) {
    echo "ID: {$rem->id} | Email: {$rem->email} | Prod ID: {$rem->product_id} | Title: {$rem->product_title} | Price: {$rem->product_price} | Status: {$rem->status}\n";
}
