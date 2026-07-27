<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: application/json');

try {
    $shopName = $_GET['shop'] ?? 'canny-apps.myshopify.com';
    $shop = \App\Models\User::where('name', $shopName)->first();
    
    if (!$shop) {
        echo json_encode(['error' => 'Shop not found for domain: ' . $shopName]);
        exit;
    }

    $planId = 1;
    $planModel = \Osiset\ShopifyApp\Storage\Models\Plan::firstOrCreate(
        ['id' => $planId],
        [
            'type' => 'RECURRING',
            'name' => 'Premium Plan',
            'price' => 5.00,
            'interval' => 'EVERY_30_DAYS',
            'test' => true,
        ]
    );

    $host = base64_encode("admin.shopify.com/store/" . explode('.', $shop->name)[0]);
    $returnUrl = route('billing.process', [
        'plan' => $planId,
        'shop' => $shop->name,
        'host' => $host,
    ]);

    $gqlQuery = '
    mutation appSubscriptionCreate(
        $name: String!,
        $returnUrl: URL!,
        $trialDays: Int,
        $test: Boolean,
        $lineItems: [AppSubscriptionLineItemInput!]!
    ) {
        appSubscriptionCreate(
            name: $name,
            returnUrl: $returnUrl,
            trialDays: $trialDays,
            test: $test,
            lineItems: $lineItems
        ) {
            appSubscription { id }
            confirmationUrl
            userErrors { field message }
        }
    }';

    $gqlVariables = [
        'name'      => $planModel->name,
        'returnUrl' => $returnUrl,
        'test'      => true,
        'lineItems' => [[
            'plan' => [
                'appRecurringPricingDetails' => [
                    'price'    => [
                        'amount'       => "5.00",
                        'currencyCode' => 'USD',
                    ],
                    'interval' => 'EVERY_30_DAYS',
                ],
            ],
        ]],
    ];

    $gqlResponse = $shop->api()->graph($gqlQuery, $gqlVariables);
    
    echo json_encode([
        'shop' => $shop->name,
        'has_password' => !empty($shop->password),
        'freemium_mode' => $shop->shopify_freemium,
        'returnUrl' => $returnUrl,
        'raw_response' => json_decode(json_encode($gqlResponse), true),
    ], JSON_PRETTY_PRINT);

} catch (\Exception $e) {
    echo json_encode([
        'exception' => $e->getMessage(),
        'file' => $e->getFile() . ':' . $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ], JSON_PRETTY_PRINT);
}
