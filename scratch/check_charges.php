<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Illuminate\Contracts\Console\Kernel;
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$charges = \DB::table('charges')->get();
echo "Charges count: " . $charges->count() . "\n";
foreach ($charges as $charge) {
    echo "ID: " . $charge->id . " | Shop ID: " . $charge->user_id . " | Type: " . $charge->type . " | Status: " . $charge->status . " | Plan ID: " . $charge->plan_id . "\n";
}

$plans = \DB::table('plans')->get();
echo "Plans count: " . $plans->count() . "\n";
foreach ($plans as $plan) {
    echo "ID: " . $plan->id . " | Name: " . $plan->name . " | Price: " . $plan->price . "\n";
}
