<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\AppProxyController;
use Illuminate\Http\Request;

// Ensure logs go to stdout/log file
Illuminate\Support\Facades\Log::info('Running manual scratch test for storeBooking');

$request = Request::create('/bookings', 'POST', [
    'shop' => 'canny-apps.myshopify.com',
    'email' => 'customer_test@example.com',
    'product_id' => '1122334455',
    'product_title' => 'The Collection Snowboard: Liquid',
    'product_handle' => 'the-collection-snowboard-liquid',
    'product_image' => 'https://cdn.shopify.com/s/files/1/0819/9199/1577/products/snowboard_liquid.jpg',
    'product_price' => '1027.00',
]);

try {
    $controller = app(AppProxyController::class);
    $response = $controller->storeBooking($request);

    echo "Status Code: " . $response->getStatusCode() . "\n";
    echo "Content: " . json_encode(json_decode($response->getContent()), JSON_PRETTY_PRINT) . "\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
