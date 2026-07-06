<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$shops = User::all();
echo "Total shops in DB: " . $shops->count() . "\n";
foreach ($shops as $shop) {
    echo "ID: " . $shop->id . "\n";
    echo "Name: " . $shop->name . "\n";
    echo "Password (Token): " . $shop->password . "\n";
    echo "Email: " . $shop->email . "\n";
    echo "Created At: " . $shop->created_at . "\n";
    echo "Updated At: " . $shop->updated_at . "\n";
    echo "---------------------------\n";
}
