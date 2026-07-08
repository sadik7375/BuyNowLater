<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Booking;

header('Content-Type: text/plain');
echo "=== DATABASE BOOKINGS LIST ===\n\n";

$bookings = Booking::orderBy('id', 'desc')->get();
foreach ($bookings as $b) {
    echo "ID: {$b->id}\n";
    echo "Product: {$b->product_title}\n";
    echo "Email: {$b->email}\n";
    echo "Status: {$b->status}\n";
    echo "Draft Order ID: {$b->draft_order_id}\n";
    echo "Checkout URL: {$b->checkout_url}\n";
    echo "Expires At: {$b->expires_at}\n";
    echo "Created At: {$b->created_at}\n";
    echo "---------------------------\n";
}
