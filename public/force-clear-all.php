<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

header('Content-Type: text/plain');

echo "1. Clearing cache and config via Artisan...\n";
try {
    Artisan::call('config:clear');
    echo "   Config clear: " . Artisan::output();
    Artisan::call('cache:clear');
    echo "   Cache clear: " . Artisan::output();
    Artisan::call('route:clear');
    echo "   Route clear: " . Artisan::output();
} catch (\Exception $e) {
    echo "   Error clearing cache: " . $e->getMessage() . "\n";
}

echo "\n2. Clearing token in database for all shops...\n";
try {
    $count = User::query()->update([
        'password' => '',
        'shopify_offline_refresh_token' => null,
        'shopify_offline_access_token_expires_at' => null,
        'shopify_offline_refresh_token_expires_at' => null
    ]);
    echo "   Successfully cleared tokens for $count shops.\n";
} catch (\Exception $e) {
    echo "   Error clearing database tokens: " . $e->getMessage() . "\n";
}

echo "\nCompleted!\n";
