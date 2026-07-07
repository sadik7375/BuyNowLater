<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain');

echo "API Key (env): " . env('SHOPIFY_API_KEY') . "\n";
echo "API Key (config): " . config('shopify-app.api_key') . "\n";

$secret = env('SHOPIFY_API_SECRET');
echo "API Secret (env): " . (empty($secret) ? "EMPTY" : substr($secret, 0, 5) . '...' . substr($secret, -5) . ' (Length: ' . strlen($secret) . ')') . "\n";
$configSecret = config('shopify-app.api_secret');
echo "API Secret (config): " . (empty($configSecret) ? "EMPTY" : substr($configSecret, 0, 5) . '...' . substr($configSecret, -5) . ' (Length: ' . strlen($configSecret) . ')') . "\n";

echo "App URL (env): " . env('APP_URL') . "\n";
echo "Shopify API Version (config): " . config('shopify-app.api_version') . "\n";
