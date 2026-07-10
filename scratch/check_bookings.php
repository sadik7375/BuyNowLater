<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Illuminate\Contracts\Console\Kernel;
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$bookings = \App\Models\Booking::orderBy('created_at', 'desc')->take(5)->get();
echo "Latest 5 bookings in DB:\n";
foreach ($bookings as $b) {
    echo "ID: {$b->id} | Email: {$b->email} | Price: {$b->product_price} | Deposit: {$b->deposit_amount} | Status: {$b->status} | Draft Order: {$b->draft_order_id} | Created: {$b->created_at}\n";
    echo "Checkout URL: {$b->checkout_url}\n";
    echo "--------------------------------------------------\n";
}
