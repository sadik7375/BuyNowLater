<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Illuminate\Contracts\Console\Kernel;
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$users = \DB::table('users')->get();
echo "Users count: " . $users->count() . "\n";
foreach ($users as $user) {
    print_r((array)$user);
}
