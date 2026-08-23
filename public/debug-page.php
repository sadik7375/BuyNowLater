<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$user = \App\Models\User::where('name', 'canny-apps.myshopify.com')->first();
if ($user) {
    auth()->login($user);
}

$request = \Illuminate\Http\Request::create('/', 'GET', [
    'shop' => 'canny-apps.myshopify.com',
    'host' => base64_encode('admin.shopify.com/store/canny-apps'),
    'embedded' => '1',
]);

$controller = new \App\Http\Controllers\DashboardController();
$response = $controller->index($request);

if (method_exists($response, 'toResponse')) {
    $response = $response->toResponse($request);
}

header('Content-Type: text/html; charset=utf-8');
echo $response->getContent();
