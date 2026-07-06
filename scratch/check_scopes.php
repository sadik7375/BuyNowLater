<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$shop = User::where('name', 'canny-apps.myshopify.com')->first();
if (!$shop) {
    echo "Shop not found in DB.\n";
    exit(1);
}

echo "Checking token for shop: {$shop->name}\n";
echo "Token: {$shop->password}\n";

try {
    $res = $shop->api()->rest('GET', '/admin/api/2024-04/access_scopes.json');
    if ($res['errors']) {
        echo "Error: Status {$res['status']}, Body: " . json_encode($res['body']) . "\n";
    } else {
        echo "Success! Active scopes:\n";
        $scopes = $res['body']['access_scopes'] ?? [];
        foreach ($scopes as $scope) {
            echo " - " . $scope['handle'] . "\n";
        }
    }
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
