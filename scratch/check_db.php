<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Illuminate\Contracts\Console\Kernel;
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$shops = \App\Models\User::all();
echo "Total shops in DB: " . $shops->count() . "\n";
foreach ($shops as $shop) {
    echo "ID: " . $shop->id . " | Name (Domain): " . $shop->name . " | Email: " . $shop->email . "\n";
}

$settings = \App\Models\Setting::all();
echo "Total settings in DB: " . $settings->count() . "\n";
foreach ($settings as $setting) {
    echo "ID: " . $setting->id . " | Shop ID: " . $setting->shop_id . " | Deposit %: " . $setting->deposit_percentage . "\n";
}
