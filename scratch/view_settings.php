<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Setting;

$shops = User::all();
echo "Total shops in DB: " . $shops->count() . "\n";
foreach ($shops as $shop) {
    echo "Shop ID: " . $shop->id . ", Name: " . $shop->name . "\n";
    $settings = Setting::where('shop_id', $shop->id)->first();
    if ($settings) {
        echo "  Settings ID: " . $settings->id . "\n";
        echo "  deposit_percentage: " . $settings->deposit_percentage . "\n";
        echo "  hold_duration_days: " . $settings->hold_duration_days . "\n";
        echo "  show_deposit: " . ($settings->show_deposit ? 'true' : 'false') . "\n";
        echo "  show_reminders: " . ($settings->show_reminders ? 'true' : 'false') . "\n";
        echo "  show_alerts: " . ($settings->show_alerts ? 'true' : 'false') . "\n";
    } else {
        echo "  No settings found!\n";
    }
    echo "---------------------------\n";
}
