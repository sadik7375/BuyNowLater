<?php

/**
 * Emergency Git Pull and Deployment Script via Browser
 */
$baseDir = dirname(__DIR__);
chdir($baseDir);

// Remove stuck index.lock file if cPanel crashed earlier
if (file_exists($baseDir . '/.git/index.lock')) {
    @unlink($baseDir . '/.git/index.lock');
}
if (file_exists($baseDir . '/public/hot')) {
    @unlink($baseDir . '/public/hot');
}

echo "<pre>\n";
echo "Current directory: " . getcwd() . "\n\n";

echo "--- GIT FETCH FETCH_HEAD --- \n";
echo shell_exec("git fetch https://github.com/sadik7375/BuyNowLater.git main 2>&1") . "\n\n";

echo "--- GIT RESET FETCH_HEAD --- \n";
echo shell_exec("git reset --hard FETCH_HEAD 2>&1") . "\n\n";

echo "--- OPTIMIZE CLEAR ---\n";
echo shell_exec("php artisan optimize:clear 2>&1");
echo "</pre>";
