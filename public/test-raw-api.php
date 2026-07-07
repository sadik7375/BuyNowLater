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

$token = $shop->password;
echo "Testing RAW Curl request for shop: " . $shop->name . "\n";
echo "Using Token: " . (empty($token) ? "EMPTY" : substr($token, 0, 10) . '...' . substr($token, -5)) . "\n";

$url = "https://" . $shop->name . "/admin/api/2025-01/shop.json";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "X-Shopify-Access-Token: " . $token,
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo "Curl Error: " . curl_error($ch) . "\n";
} else {
    echo "HTTP Status Code: " . $httpCode . "\n";
    echo "Response:\n" . $response . "\n";
}

curl_close($ch);
