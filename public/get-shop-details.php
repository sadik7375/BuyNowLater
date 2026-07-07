<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$shopDomain = isset($_GET['shop']) ? $_GET['shop'] : 'canny-apps.myshopify.com';
$shop = User::where('name', $shopDomain)->first();

echo "<h1>Shop Details for: " . htmlspecialchars($shopDomain) . "</h1>";
if (!$shop) {
    echo "<p>Shop not found in database.</p>";
} else {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>ID</td><td>" . $shop->id . "</td></tr>";
    echo "<tr><td>Name</td><td>" . htmlspecialchars($shop->name) . "</td></tr>";
    
    $token = $shop->password;
    $maskedToken = empty($token) ? 'EMPTY' : substr($token, 0, 10) . '...' . substr($token, -5) . ' (Length: ' . strlen($token) . ')';
    echo "<tr><td>Access Token (password)</td><td>" . $maskedToken . "</td></tr>";
    
    $rt = $shop->shopify_offline_refresh_token;
    $maskedRt = empty($rt) ? 'EMPTY' : substr($rt, 0, 15) . '... (Length: ' . strlen($rt) . ')';
    echo "<tr><td>Refresh Token</td><td>" . $maskedRt . "</td></tr>";
    
    echo "<tr><td>Access Token Expires At</td><td>" . ($shop->shopify_offline_access_token_expires_at ?? 'N/A') . "</td></tr>";
    echo "<tr><td>Refresh Token Expires At</td><td>" . ($shop->shopify_offline_refresh_token_expires_at ?? 'N/A') . "</td></tr>";
    echo "<tr><td>Deleted At</td><td>" . ($shop->deleted_at ?? 'N/A') . "</td></tr>";
    echo "</table>";
}
