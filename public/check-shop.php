<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$shop = User::where('name', 'canny-apps.myshopify.com')->first();
if (!$shop) {
    echo "Shop not found in database.<br>";
} else {
    echo "Shop: " . htmlspecialchars($shop->name) . "<br>";
    echo "Password (Access Token): " . (empty($shop->password) ? "EMPTY" : "NOT EMPTY (Length: " . strlen($shop->password) . ")") . "<br>";
    echo "Refresh Token: " . (empty($shop->shopify_offline_refresh_token) ? "EMPTY" : "NOT EMPTY (Length: " . strlen($shop->shopify_offline_refresh_token) . ")") . "<br>";
    echo "Access Token Expires At: " . ($shop->shopify_offline_access_token_expires_at ?? 'N/A') . "<br>";
    echo "Refresh Token Expires At: " . ($shop->shopify_offline_refresh_token_expires_at ?? 'N/A') . "<br>";
}
