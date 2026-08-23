<?php
header('Content-Type: text/plain');
try {
    require __DIR__ . '/../vendor/autoload.php';
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

    $request = Illuminate\Http\Request::create('/', 'GET', [
        'shop' => 'canny-apps.myshopify.com',
        'host' => base64_encode('admin.shopify.com/store/canny-apps'),
        'embedded' => '1',
    ]);

    $response = $kernel->handle($request);

    echo "Status Code: " . $response->getStatusCode() . "\n\n";
    echo "=== RENDERED HTML OUTPUT ===\n\n";
    echo $response->getContent();
} catch (\Throwable $e) {
    echo "EXCEPTION OCCURRED:\n";
    echo $e->getMessage() . "\n\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    echo "Stack trace:\n" . $e->getTraceAsString();
}
