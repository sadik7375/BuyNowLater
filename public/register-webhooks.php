<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Osiset\ShopifyApp\Actions\CreateWebhooks;
use Osiset\ShopifyApp\Objects\Values\ShopId;

header('Content-Type: text/plain');
echo "=== REGISTERING SHOPIFY WEBHOOKS FOR ALL SHOPS ===\n\n";

$shops = User::all();
if ($shops->isEmpty()) {
    echo "No shops found in the database.\n";
    exit;
}

$createWebhooksAction = app(CreateWebhooks::class);
$webhooksConfig = config('shopify-app.webhooks');

echo "Webhooks to register:\n";
print_r($webhooksConfig);
echo "\n";

foreach ($shops as $shop) {
    echo "Shop: {$shop->name} (ID: {$shop->id})\n";
    try {
        $result = $createWebhooksAction(new ShopId($shop->id), $webhooksConfig);
        echo "Result:\n";
        print_r($result);
        echo "Webhooks registered successfully for {$shop->name}!\n\n";
    } catch (\Exception $e) {
        echo "ERROR registering for {$shop->name}: " . $e->getMessage() . "\n";
        echo "Trace:\n" . $e->getTraceAsString() . "\n\n";
    }
}
