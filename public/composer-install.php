<?php
// Standalone emergency composer installer for BuyNowLater
ini_set('display_errors', 1);
error_reporting(E_ALL);

$basePath = dirname(__DIR__);
$composerHome = $basePath . '/storage/app/composer';
if (!file_exists($composerHome)) {
    @mkdir($composerHome, 0755, true);
}
putenv("COMPOSER_HOME={$composerHome}");

echo "<h2>BuyNowLater Emergency Composer Installer</h2>";
echo "<p><strong>Base Directory:</strong> " . htmlspecialchars($basePath) . "</p>";

$output = [];
$returnCode = 0;

$cmd = "cd " . escapeshellarg($basePath) . " && composer install --no-dev --optimize-autoloader 2>&1";
exec($cmd, $output, $returnCode);

if ($returnCode !== 0) {
    $output[] = "--> Primary command returned code {$returnCode}. Retrying with /usr/local/bin/composer...";
    $cmd2 = "cd " . escapeshellarg($basePath) . " && /usr/local/bin/composer install --no-dev --optimize-autoloader 2>&1";
    exec($cmd2, $output, $returnCode);
}

echo "<h3>Execution Output (Exit Code: {$returnCode}):</h3>";
echo "<pre style='background:#111; color:#0f0; padding:15px; border-radius:5px; max-height:400px; overflow:auto;'>" . htmlspecialchars(implode("\n", $output)) . "</pre>";

if (file_exists($basePath . '/vendor/inertiajs/inertia-laravel')) {
    echo "<h3 style='color:green;'>✅ SUCCESS: inertiajs/inertia-laravel is now installed in vendor! You can return to Shopify Admin.</h3>";
} else {
    echo "<h3 style='color:red;'>⚠️ ATTENTION: Package was not found in vendor. If exec() is disabled on your hosting server, please run 'composer install' via cPanel Terminal or SSH.</h3>";
}
