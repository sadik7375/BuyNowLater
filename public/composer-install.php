<?php
// Standalone emergency composer installer for BuyNowLater (Auto-downloads composer.phar)
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

$pharPath = $basePath . '/composer.phar';
if (!file_exists($pharPath)) {
    echo "<p>Downloading composer.phar...</p>";
    $pharData = @file_get_contents('https://getcomposer.org/composer-stable.phar');
    if ($pharData) {
        file_put_contents($pharPath, $pharData);
        echo "<p style='color:green;'>composer.phar downloaded successfully!</p>";
    } else {
        echo "<p style='color:red;'>Failed to download composer.phar automatically via file_get_contents.</p>";
    }
}

$output = [];
$returnCode = 0;

$phpExecs = [PHP_BINARY, '/usr/local/bin/php', '/usr/bin/php', 'php'];
$success = false;

foreach ($phpExecs as $phpBin) {
    if (empty($phpBin)) continue;
    $output = [];
    $cmd = "cd " . escapeshellarg($basePath) . " && {$phpBin} composer.phar install --no-dev --optimize-autoloader 2>&1";
    exec($cmd, $output, $returnCode);
    if ($returnCode === 0) {
        $success = true;
        break;
    }
}

if (!$success) {
    // Try system composer paths
    $sysComposers = ['composer', '/usr/local/bin/composer', '/opt/cpanel/composer/bin/composer'];
    foreach ($sysComposers as $sysComp) {
        $output = [];
        $cmd = "cd " . escapeshellarg($basePath) . " && {$sysComp} install --no-dev --optimize-autoloader 2>&1";
        exec($cmd, $output, $returnCode);
        if ($returnCode === 0) {
            $success = true;
            break;
        }
    }
}

echo "<h3>Execution Output (Exit Code: {$returnCode}):</h3>";
echo "<pre style='background:#111; color:#0f0; padding:15px; border-radius:5px; max-height:400px; overflow:auto;'>" . htmlspecialchars(implode("\n", $output)) . "</pre>";

if (file_exists($basePath . '/vendor/inertiajs/inertia-laravel')) {
    echo "<h3 style='color:green;'>✅ SUCCESS: inertiajs/inertia-laravel is now installed in vendor! You can return to Shopify Admin.</h3>";
} else {
    echo "<h3 style='color:red;'>⚠️ ATTENTION: Package was not found in vendor.</h3>";
}
