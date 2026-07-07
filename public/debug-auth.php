<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use Osiset\ShopifyApp\Objects\Enums\AuthMode;

$shopDomain = isset($_GET['shop']) ? $_GET['shop'] : 'canny-apps.myshopify.com';
$code = isset($_GET['code']) ? $_GET['code'] : null;

echo "<h2>Debugging OAuth for shop: $shopDomain</h2>";
echo "<p>Code: " . htmlspecialchars($code) . "</p>";

$shop = User::where('name', $shopDomain)->first();
if (!$shop) {
    echo "Shop not found in database.<br>";
    exit;
}

$apiHelper = $shop->apiHelper();
$grantMode = AuthMode::OFFLINE();

if (empty($code)) {
    echo "Code is empty. Generating auth URL:<br>";
    $authUrl = $apiHelper->getApi()->getAuthUrl(
        config('shopify-app.api_scopes'),
        'https://buynowlater.orderkoi.online/debug-auth.php',
        'offline'
    );
    echo "<a href='$authUrl'>Go to Auth URL</a>";
    exit;
}

try {
    echo "Attempting to get access data...<br>";
    $data = $apiHelper->getAccessData($code, $grantMode);
    echo "Success! Access data retrieved:<br><pre>";
    print_r($data);
    echo "</pre>";
} catch (\Exception $e) {
    echo "<h3>Exception caught!</h3>";
    echo "Message: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")<br>";
    if (method_exists($e, 'getResponse') && $e->getResponse()) {
        echo "Response body: <pre>" . htmlspecialchars($e->getResponse()->getBody()->getContents()) . "</pre><br>";
    }
    echo "Trace: <pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
