<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Illuminate\Contracts\Console\Kernel;
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$shop = \App\Models\User::where('name', 'canny-apps.myshopify.com')->first();
if ($shop) {
    $settings = \App\Models\Setting::where('shop_id', $shop->id)->first();
    if ($settings) {
        echo "show_deposit: " . ($settings->show_deposit ? 'true' : 'false') . "\n";
        echo "show_reminders: " . ($settings->show_reminders ? 'true' : 'false') . "\n";
        echo "show_alerts: " . ($settings->show_alerts ? 'true' : 'false') . "\n";
    } else {
        echo "No settings found for shop.\n";
    }
} else {
    echo "Shop not found.\n";
}
