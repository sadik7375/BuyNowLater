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

$mutation = 'mutation draftOrderCreate($input: DraftOrderInput!) {
  draftOrderCreate(input: $input) {
    draftOrder {
      id
      invoiceUrl
    }
    userErrors {
      field
      message
    }
  }
}';

$input = [
    'input' => [
        'lineItems' => [
            [
                'title' => 'Deposit — Test Product',
                'originalUnitPrice' => '15.00',
                'quantity' => 1,
                'requiresShipping' => false,
                'customAttributes' => [
                    ['key' => '_token', 'value' => 'test_token_12345'],
                    ['key' => 'Original Price', 'value' => '$100.00'],
                    ['key' => 'Remaining Balance', 'value' => '$85.00']
                ]
            ]
        ],
        'note' => 'BuyLater deposit — do not fulfill',
        'tags' => ['buylater-deposit']
    ]
];

try {
    $res = $shop->api()->graph($mutation, $input);
    echo "GraphQL Response status: " . $res['status'] . "\n";
    echo "Body: " . json_encode($res['body'], JSON_PRETTY_PRINT) . "\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
