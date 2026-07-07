<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

header('Content-Type: text/plain');

$shopDomain = isset($_GET['shop']) ? $_GET['shop'] : 'canny-apps.myshopify.com';
$shop = User::where('name', $shopDomain)->first();

if (!$shop) {
    echo "Shop $shopDomain not found in database.\n";
    exit;
}

echo "Testing API connection for shop: " . $shop->name . "\n";
echo "Stored Access Token: " . (empty($shop->password) ? "EMPTY" : substr($shop->password, 0, 10) . '...' . substr($shop->password, -5)) . "\n";

try {
    $apiHelper = $shop->apiHelper();
    $res = $shop->api()->rest('GET', '/admin/api/' . config('shopify-app.api_version') . '/shop.json');
    
    echo "API Response Status: " . ($res['status'] ?? 'N/A') . "\n";
    echo "Errors: " . ($res['errors'] ? 'TRUE' : 'FALSE') . "\n";
    echo "Response Body:\n";
    print_r($res['body']);
} catch (\Exception $e) {
    echo "Exception occurred: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    if (method_exists($e, 'getResponse') && $e->getResponse()) {
        echo "Raw Response Body:\n" . $e->getResponse()->getBody()->getContents() . "\n";
    }
}
