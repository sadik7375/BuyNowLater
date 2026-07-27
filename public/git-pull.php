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

echo "<pre>\n";
echo "Current directory: " . getcwd() . "\n\n";
echo shell_exec("git fetch origin main 2>&1; git reset --hard origin/main 2>&1; php artisan optimize:clear 2>&1");
echo "</pre>";
