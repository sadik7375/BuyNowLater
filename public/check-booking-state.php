<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Booking;
use App\Models\User;

header('Content-Type: text/plain');
echo "=== BOOKING STATE DIAGNOSTIC ===\n\n";

try {
    $bookings = Booking::orderBy('id', 'desc')->get();
    echo "Found " . $bookings->count() . " bookings in database.\n\n";

    foreach ($bookings as $b) {
        echo "Booking ID: {$b->id}\n";
        echo "Customer Name: {$b->customer_name}\n";
        echo "Email: {$b->email}\n";
        echo "Status: {$b->status}\n";
        echo "Product: {$b->product_title}\n";
        echo "Remaining Balance: {$b->remaining_balance}\n";
        echo "Stored Draft Order ID: {$b->draft_order_id}\n";
        echo "Stored Checkout URL: {$b->checkout_url}\n";

        if ($b->draft_order_id) {
            $shop = User::find($b->shop_id);
            if ($shop) {
                echo "Fetching Draft Order #{$b->draft_order_id} from Shopify for shop: {$shop->name}...\n";
                $response = $shop->api()->rest('GET', '/admin/api/' . config('shopify-app.api_version') . '/draft_orders/' . $b->draft_order_id . '.json');
                
                if ($response['errors']) {
                    echo "Shopify API Error: " . json_encode($response['body']) . "\n";
                } else {
                    $draftOrder = $response['body']['draft_order'] ?? null;
                    if ($draftOrder) {
                        $status = is_object($draftOrder) ? ($draftOrder->status ?? '') : ($draftOrder['status'] ?? '');
                        $invoiceUrl = is_object($draftOrder) ? ($draftOrder->invoice_url ?? '') : ($draftOrder['invoice_url'] ?? '');
                        echo "Shopify Status: {$status}\n";
                        echo "Shopify Invoice URL: {$invoiceUrl}\n";
                    } else {
                        echo "No draft order object in Shopify response.\n";
                    }
                }
            } else {
                echo "Shop record not found for ID: {$b->shop_id}\n";
            }
        }
        echo "--------------------------------------------------\n\n";
    }
} catch (\Exception $e) {
    echo "Diagnostic failed: " . $e->getMessage() . "\n";
}
