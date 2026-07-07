<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain');

echo "Loaded Config Values:\n";
echo "shopify-app.expiring_offline_tokens: " . (config('shopify-app.expiring_offline_tokens') ? 'TRUE' : 'FALSE') . "\n";
echo "env SHOPIFY_EXPIRING_OFFLINE_TOKENS: " . (env('SHOPIFY_EXPIRING_OFFLINE_TOKENS') ? 'TRUE' : 'FALSE') . "\n";
echo "shopify-app.api_version: " . config('shopify-app.api_version') . "\n";
echo "shopify-app.api_key: " . config('shopify-app.api_key') . "\n";
echo "shopify-app.api_redirect: " . config('shopify-app.api_redirect') . "\n";

$configFileExist = file_exists(base_path('bootstrap/cache/config.php'));
echo "Config cache file exists: " . ($configFileExist ? 'YES' : 'NO') . "\n";
