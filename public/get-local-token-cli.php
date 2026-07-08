<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$shop = User::where('name', 'canny-apps.myshopify.com')->first();
if ($shop) {
    echo "Local Shop: " . $shop->name . "\n";
    echo "Local Password/Token: " . $shop->password . "\n";
    echo "Local Refresh Token: " . ($shop->shopify_offline_refresh_token ?? 'NULL') . "\n";
} else {
    echo "No local shop found for canny-apps.myshopify.com\n";
}
