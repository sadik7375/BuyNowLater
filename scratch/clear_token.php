<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$shop = \App\Models\User::where('name', 'canny-apps.myshopify.com')->first();
if ($shop) {
    $shop->password = '';
    $shop->save();
    echo "Access token for canny-apps.myshopify.com has been cleared successfully.\n";
} else {
    echo "Shop canny-apps.myshopify.com not found.\n";
}
