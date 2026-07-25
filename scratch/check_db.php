<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== USERS (SHOPS) ===\n";
$users = App\Models\User::all();
foreach ($users as $u) {
    echo "ID: {$u->id}, Name: {$u->name}, Created: {$u->created_at}\n";
}

echo "\n=== SETTINGS ===\n";
$settings = App\Models\Setting::all();
foreach ($settings as $s) {
    echo "Shop ID: {$s->shop_id}, Use Selling Plan: " . ($s->use_selling_plan ? 'YES' : 'NO') . ", Plan ID: {$s->selling_plan_id}, Plan Group ID: {$s->selling_plan_group_id}\n";
}

echo "\n=== BOOKINGS ===\n";
$bookings = App\Models\Booking::orderBy('created_at', 'desc')->take(5)->get();
foreach ($bookings as $b) {
    echo "ID: {$b->id}, Email: {$b->email}, Product: {$b->product_title}, Status: {$b->status}, Payment Type: {$b->payment_type}\n";
}
