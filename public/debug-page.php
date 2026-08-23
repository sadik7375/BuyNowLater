<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

$request = \Illuminate\Http\Request::create('/', 'GET', [
    'shop' => 'canny-apps.myshopify.com',
    'host' => base64_encode('admin.shopify.com/store/canny-apps'),
    'embedded' => '1',
]);

$response = $kernel->handle($request);

header('Content-Type: text/html; charset=utf-8');
echo $response->getContent();
