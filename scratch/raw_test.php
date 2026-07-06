<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$shop = User::where('name', 'canny-apps.myshopify.com')->first();
if (!$shop) {
    echo "Shop not found in DB.\n";
    exit(1);
}

$token = $shop->password;
echo "Testing token: {$token}\n";

$url = "https://{$shop->name}/admin/api/2024-04/shop.json";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "X-Shopify-Access-Token: {$token}",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status Code: {$status}\n";
echo "Response: {$response}\n";
