<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Setting;

// Restore the User (Shopify store)
$shopName = 'canny-apps.myshopify.com';
$token = 'shpua_9e9ca73bb75759501bdc282035ca67d6';

$shop = User::where('name', $shopName)->first();
if (!$shop) {
    $shop = new User();
    $shop->name = $shopName;
    $shop->email = 'admin@canny-apps.myshopify.com';
    $shop->password = $token; // Osiset package uses password column for access token
    $shop->save();
    echo "Restored shop {$shopName} with ID {$shop->id}.\n";
} else {
    $shop->password = $token;
    $shop->save();
    echo "Updated shop {$shopName} token.\n";
}

// Restore default settings
$setting = Setting::where('shop_id', $shop->id)->first();
if (!$setting) {
    Setting::create([
        'shop_id' => $shop->id,
        'sender_display_name' => 'Canny Apps via BuyLater',
        'deposit_percentage' => 10,
        'button_text' => 'Buy Later — not ready yet?',
        'button_color' => '#1a1a1a',
        'button_text_color' => '#ffffff',
        'reminder_email_subject' => 'Reminder: You wanted to buy this later!',
        'discount_email_subject' => 'Price Drop Alert: A product you wanted is now on sale!',
        'show_deposit' => true,
        'show_reminders' => true,
        'show_alerts' => true,
        'hold_duration_days' => 14,
    ]);
    echo "Restored settings for shop ID {$shop->id}.\n";
}
