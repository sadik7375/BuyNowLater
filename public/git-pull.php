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
echo "--- GIT STATUS ---\n";
echo shell_exec("git status 2>&1") . "\n\n";
echo "--- GIT FETCH ---\n";
echo shell_exec("git fetch origin 2>&1") . "\n\n";
echo "--- GIT RESET ---\n";
echo shell_exec("git reset --hard origin/main 2>&1") . "\n\n";
echo "--- OPTIMIZE CLEAR ---\n";
echo shell_exec("php artisan optimize:clear 2>&1");
echo "</pre>";
