<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$shop = \App\Models\User::where('name', 'canny-apps.myshopify.com')->first();
if ($shop) {
    echo "ID: " . $shop->id . "\n";
    echo "Name: " . $shop->name . "\n";
    echo "Token: " . $shop->password . "\n";
} else {
    echo "Shop not found.\n";
}
