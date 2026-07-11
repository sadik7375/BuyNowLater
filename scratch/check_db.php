<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Setting;

echo "--- Users ---\n";
foreach (User::all() as $u) {
    echo "ID: {$u->id}, Name: {$u->name}\n";
}

echo "\n--- Settings ---\n";
foreach (Setting::all() as $s) {
    echo "ID: {$s->id}, Shop ID: {$s->shop_id}, Deposit: {$s->deposit_percentage}%, Hold: {$s->hold_duration_days} days, Type: {$s->product_targeting_type}, Targets: {$s->targeted_product_ids}\n";
}
