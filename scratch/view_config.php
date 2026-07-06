<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "api_grant_mode: " . config('shopify-app.api_grant_mode') . "\n";
echo "api_scopes: " . config('shopify-app.api_scopes') . "\n";
echo "api_key: " . config('shopify-app.api_key') . "\n";
echo "api_secret: " . config('shopify-app.api_secret') . "\n";
echo "myshopify_domain: " . config('shopify-app.myshopify_domain') . "\n";
echo "expiring_offline_tokens: " . (config('shopify-app.expiring_offline_tokens') ? 'true' : 'false') . "\n";
