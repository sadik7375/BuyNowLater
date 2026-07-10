<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Illuminate\Contracts\Console\Kernel;
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$shop = \App\Models\User::where('name', 'canny-apps.myshopify.com')->first();
if ($shop) {
    // Let's create an active charge in the charges table so kyon147/laravel-shopify's traits work correctly
    $chargeId = \DB::table('charges')->insertGetId([
        'charge_id' => 12345678,
        'type' => 1, // RECURRING
        'status' => 'ACTIVE',
        'name' => 'Pro Plan',
        'price' => 5.00,
        'interval' => 'EVERY_30_DAYS',
        'test' => true,
        'user_id' => $shop->id,
        'plan_id' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $shop->plan_id = 1;
    $shop->shopify_freemium = 0;
    $shop->save();

    echo "Pro Plan activated successfully for canny-apps.myshopify.com! Charge ID: {$chargeId}\n";
} else {
    echo "Shop canny-apps.myshopify.com not found.\n";
}
