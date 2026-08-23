<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$user = \App\Models\User::where('name', 'canny-apps.myshopify.com')->first();
echo "User in DB: " . ($user ? $user->name . " (ID: {$user->id})" : "NOT FOUND") . "\n\n";

if ($user) {
    auth()->login($user);
}

$request = \Illuminate\Http\Request::create('/', 'GET', [
    'shop' => 'canny-apps.myshopify.com',
    'host' => base64_encode('admin.shopify.com/store/canny-apps'),
    'embedded' => '1',
]);

$controller = new \App\Http\Controllers\DashboardController();
try {
    $res = $controller->index($request);
    echo "Controller Response Class: " . (is_object($res) ? get_class($res) : gettype($res)) . "\n\n";
    if (is_object($res) && method_exists($res, 'toResponse')) {
        $inertiaRes = $res->toResponse($request);
        echo "Inertia HTML Content Output:\n\n";
        echo $inertiaRes->getContent();
    } elseif (is_object($res) && method_exists($res, 'getContent')) {
        echo $res->getContent();
    } else {
        var_dump($res);
    }
} catch (\Throwable $e) {
    echo "Controller Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    echo "Stack trace:\n" . $e->getTraceAsString();
}
