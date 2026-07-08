<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;

header('Content-Type: text/plain');
echo "=== REGISTERING SHOPIFY WEBHOOKS ===\n\n";

try {
    $exitCode = Artisan::call('shopify-app:register-webhooks');
    echo "Artisan Command Exit Code: " . $exitCode . "\n";
    echo "Artisan Command Output:\n";
    echo Artisan::output() . "\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
