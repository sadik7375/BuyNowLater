<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$user = \App\Models\User::where('name', 'canny-apps.myshopify.com')->first();
if ($user) {
    auth()->login($user);
    session(['shopify_domain' => $user->name]);
}

$request = \Illuminate\Http\Request::create('/', 'GET', [
    'shop' => 'canny-apps.myshopify.com',
    'host' => base64_encode('admin.shopify.com/store/canny-apps'),
    'embedded' => '1',
]);

$controller = new \App\Http\Controllers\DashboardController();
try {
    $response = $controller->index($request);

    if (is_object($response) && method_exists($response, 'toResponse')) {
        $response = $response->toResponse($request);
    }

    if (is_object($response) && method_exists($response, 'getContent')) {
        header('Content-Type: text/html; charset=utf-8');
        echo $response->getContent();
    } else {
        var_dump($response);
    }
} catch (\Throwable $e) {
    header('Content-Type: text/plain');
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    echo $e->getTraceAsString();
}
