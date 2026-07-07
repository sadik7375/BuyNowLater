<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

try {
    $count = User::query()->update(['password' => '']);
    echo "Successfully cleared access tokens for $count shops. Please open the app in the Shopify Admin to re-authenticate.";
} catch (\Exception $e) {
    echo "Error clearing tokens: " . $e->getMessage();
}
