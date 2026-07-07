<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

header('Content-Type: text/plain');

$shopDomain = isset($_GET['shop']) ? $_GET['shop'] : 'canny-apps.myshopify.com';
echo "Testing Draft Order creation for: $shopDomain\n\n";

$shop = User::where('name', $shopDomain)->first();
if (!$shop) {
    echo "ERROR: Shop not found in database.\n";
    exit;
}

echo "Shop ID: " . $shop->id . "\n";
echo "Shop Name: " . $shop->name . "\n";
echo "Access Token Length: " . strlen($shop->password) . "\n\n";

try {
    $draftOrderData = [
        'draft_order' => [
            'email' => 'diagnostic-test@example.com',
            'line_items' => [[
                'title'             => 'Diagnostic Deposit Test',
                'price'             => '5.00',
                'quantity'          => 1,
                'requires_shipping' => false,
            ]],
            'note'  => 'Diagnostic check — please ignore',
        ]
    ];

    echo "Sending API request...\n";
    $createRes = $shop->api()->rest('POST', '/admin/api/' . config('shopify-app.api_version') . '/draft_orders.json', $draftOrderData);
    
    echo "API Version used: " . config('shopify-app.api_version') . "\n";
    echo "Response Errors status: " . ($createRes['errors'] ? 'TRUE' : 'FALSE') . "\n";
    echo "Response Status: " . ($createRes['status'] ?? 'N/A') . "\n";
    
    echo "\nResponse Body:\n";
    print_r($createRes['body']);
    
    if (isset($createRes['exception'])) {
        echo "\nException in response object:\n";
        print_r($createRes['exception']->getMessage());
    }

} catch (\Exception $e) {
    echo "\nCaught Exception:\n";
    echo $e->getMessage() . "\n";
    echo $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
