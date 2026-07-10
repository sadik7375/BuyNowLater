<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Illuminate\Contracts\Console\Kernel;
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$bookings = \App\Models\Booking::where('product_title', 'like', '%Snowboard%')->get();
echo "Total bookings found: " . $bookings->count() . "\n";
foreach ($bookings as $b) {
    echo "ID: {$b->id} | Email: {$b->email} | Price: {$b->product_price} | Deposit: {$b->deposit_amount} | Remaining: {$b->remaining_balance} | Status: {$b->status} | Created: {$b->created_at}\n";
}
