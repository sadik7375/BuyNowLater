<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Log;

$shop = User::where('name', 'canny-apps.myshopify.com')->first();
if (!$shop) {
    echo "Shop canny-apps.myshopify.com not found in database.\n";
    exit(1);
}

echo "Fetching temporary 'BuyLater Deposit' products from shop: {$shop->name}...\n";

try {
    $response = $shop->api()->rest('GET', '/admin/api/2024-04/products.json', [
        'limit' => 250,
        'product_type' => 'BuyLater Deposit'
    ]);

    if ($response['errors']) {
        echo "Error fetching products: " . json_encode($response['body']) . "\n";
        exit(1);
    }

    $products = $response['body']['products'] ?? [];
    echo "Found " . count($products) . " temporary products to delete.\n";

    $deletedCount = 0;
    foreach ($products as $product) {
        $id = $product['id'];
        $title = $product['title'];
        echo "Deleting product ID {$id}: '{$title}'...\n";

        $deleteRes = $shop->api()->rest('DELETE', "/admin/api/2024-04/products/{$id}.json");

        if (!$deleteRes['errors']) {
            echo "Successfully deleted product {$id}.\n";
            $deletedCount++;
        } else {
            echo "Failed to delete product {$id}: " . json_encode($deleteRes['body']) . "\n";
        }
    }

    echo "\nCleanup finished. Total products deleted: {$deletedCount}\n";

} catch (\Exception $e) {
    echo "Exception during product cleanup: " . $e->getMessage() . "\n";
}
