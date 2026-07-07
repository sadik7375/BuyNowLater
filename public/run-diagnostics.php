<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Schema;

header('Content-Type: text/plain');

echo "=== DIAGNOSTIC REPORT ===\n\n";

// 1. Env & Config Settings
echo "--- 1. Env & Config Settings ---\n";
echo "SHOPIFY_API_KEY (env): " . env('SHOPIFY_API_KEY') . "\n";
echo "SHOPIFY_API_KEY (config): " . config('shopify-app.api_key') . "\n";
$secret = env('SHOPIFY_API_SECRET');
echo "SHOPIFY_API_SECRET (env): " . (empty($secret) ? "EMPTY" : substr($secret, 0, 5) . '...' . substr($secret, -5) . ' (Length: ' . strlen($secret) . ')') . "\n";
echo "SHOPIFY_EXPIRING_OFFLINE_TOKENS (env): " . (env('SHOPIFY_EXPIRING_OFFLINE_TOKENS') === null ? 'NULL' : (env('SHOPIFY_EXPIRING_OFFLINE_TOKENS') ? 'TRUE' : 'FALSE')) . "\n";
echo "SHOPIFY_EXPIRING_OFFLINE_TOKENS (config): " . (config('shopify-app.expiring_offline_tokens') ? 'TRUE' : 'FALSE') . "\n";
echo "APP_URL (env): " . env('APP_URL') . "\n";
echo "API Version (config): " . config('shopify-app.api_version') . "\n\n";

// 2. Database Columns for 'users'
echo "--- 2. Database Columns for 'users' ---\n";
if (Schema::hasTable('users')) {
    $columns = Schema::getColumnListing('users');
    foreach ($columns as $column) {
        echo " - $column\n";
    }
} else {
    echo " ERROR: 'users' table does not exist.\n";
}
echo "\n";

// 3. Shop DB Record
echo "--- 3. Shop DB Record ---\n";
$shopDomain = 'canny-apps.myshopify.com';
$shop = User::where('name', $shopDomain)->first();
if (!$shop) {
    echo " ERROR: Shop '$shopDomain' not found in database.\n\n";
} else {
    echo "ID: " . $shop->id . "\n";
    echo "Name: " . $shop->name . "\n";
    $token = $shop->password;
    echo "Access Token (password): " . (empty($token) ? "EMPTY" : substr($token, 0, 10) . '...' . substr($token, -5) . ' (Length: ' . strlen($token) . ')') . "\n";
    
    // Check if expiring token columns exist on the model
    $refreshToken = isset($shop->shopify_offline_refresh_token) ? $shop->shopify_offline_refresh_token : 'N/A';
    if ($refreshToken !== 'N/A') {
        echo "Refresh Token: " . (empty($refreshToken) ? "EMPTY" : substr($refreshToken, 0, 10) . '...' . ' (Length: ' . strlen($refreshToken) . ')') . "\n";
    } else {
        echo "Refresh Token Column: NOT PRESENT ON MODEL\n";
    }
    
    echo "Access Token Expires At: " . ($shop->expires_at ?? 'N/A') . "\n";
    echo "Refresh Token Expires At: " . ($shop->shopify_offline_refresh_token_expires_at ?? 'N/A') . "\n\n";
}

// 4. Package API Connection Test
echo "--- 4. Package API Connection Test ---\n";
if ($shop) {
    try {
        $apiHelper = $shop->apiHelper();
        $res = $shop->api()->rest('GET', '/admin/api/' . config('shopify-app.api_version') . '/shop.json');
        echo "Status: " . ($res['status'] ?? 'N/A') . "\n";
        echo "Errors: " . ($res['errors'] ? 'TRUE' : 'FALSE') . "\n";
        if (isset($res['body']['shop'])) {
            echo "Success! Shop Name from API: " . $res['body']['shop']['name'] . "\n";
        } else {
            echo "Response: " . json_encode($res['body']) . "\n";
        }
    } catch (\Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n";
        if (method_exists($e, 'getResponse') && $e->getResponse()) {
            echo "Raw Response: " . $e->getResponse()->getBody()->getContents() . "\n";
        }
    }
} else {
    echo " Skipped (No shop record).\n";
}
echo "\n";

// 5. Raw Curl Test
echo "--- 5. Raw Curl Test ---\n";
if ($shop && !empty($shop->password)) {
    $url = "https://" . $shop->name . "/admin/api/2025-01/shop.json";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-Shopify-Access-Token: " . $shop->password,
        "Content-Type: application/json"
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) {
        echo "Curl Error: " . curl_error($ch) . "\n";
    } else {
        echo "HTTP Status Code: " . $httpCode . "\n";
        echo "Response: " . $response . "\n";
    }
    curl_close($ch);
} else {
    echo " Skipped (No shop or empty token).\n";
}
echo "\n========================\n";
