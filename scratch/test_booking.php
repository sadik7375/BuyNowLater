<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$shop = User::where('name', 'canny-apps.myshopify.com')->first();
if (!$shop) {
    echo "Shop not found.\n";
    exit(1);
}

$depositAmount = 15.00;
$productPrice = 100.00;
$remainingBalance = 85.00;
$token = 'test_token_123';

$draftOrderData = [
    'draft_order' => [
        'email' => 'customer@example.com',
        'customer' => [
            'email' => 'customer@example.com',
        ],
        'line_items' => [[
            'title'             => 'Deposit — Test Product',
            'price'             => number_format($depositAmount, 2, '.', ''),
            'quantity'          => 1,
            'requires_shipping' => false,
            'properties'        => [
                ['name' => '_token', 'value' => $token],
                ['name' => 'Original Price', 'value' => '$' . number_format($productPrice, 2)],
                ['name' => 'Remaining Balance', 'value' => '$' . number_format($remainingBalance, 2)],
            ]
        ]],
        'note'  => 'BuyLater deposit — do not fulfill',
        'tags'  => 'buylater-deposit',
    ]
];

echo "Sending draft order creation request...\n";
$createRes = $shop->api()->rest('POST', '/admin/api/2024-04/draft_orders.json', $draftOrderData);

echo "Errors: " . ($createRes['errors'] ? 'true' : 'false') . "\n";
echo "Status: " . $createRes['status'] . "\n";
echo "Body:\n";
print_r($createRes['body']);
echo "\n";
