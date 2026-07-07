<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$shop = User::where('name', 'canny-apps.myshopify.com')->first();
if (!$shop) {
    echo "Shop not found in database.<br>";
} else {
    echo "Shop: " . htmlspecialchars($shop->name) . "<br>";
    echo "Password field (Access Token) is: " . (empty($shop->password) ? "EMPTY" : "NOT EMPTY (Length: " . strlen($shop->password) . ")") . "<br>";
}
