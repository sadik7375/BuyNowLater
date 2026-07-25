<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Setting;
use App\Models\User;

$users = User::all();
foreach ($users as $user) {
    echo "Shop: {$user->name} (ID: {$user->id})\n";
    $setting = Setting::where('shop_id', $user->id)->first();
    if ($setting) {
        echo "  use_selling_plan: " . ($setting->use_selling_plan ? 'true' : 'false') . "\n";
        echo "  selling_plan_group_id: {$setting->selling_plan_group_id}\n";
        echo "  selling_plan_id: {$setting->selling_plan_id}\n";
    } else {
        echo "  No settings found.\n";
    }
}
