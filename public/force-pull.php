<?php
$baseDir = dirname(__DIR__);
chdir($baseDir);

if (file_exists($baseDir . '/.git/index.lock')) {
    @unlink($baseDir . '/.git/index.lock');
}
if (file_exists($baseDir . '/public/hot')) {
    @unlink($baseDir . '/public/hot');
}

echo "<pre>\n";
echo "Current directory: " . getcwd() . "\n\n";
echo shell_exec("git remote set-url origin https://github.com/sadik7375/BuyNowLater.git 2>&1") . "\n";
echo shell_exec("git fetch origin main --force 2>&1") . "\n";
echo shell_exec("git reset --hard origin/main 2>&1") . "\n";
echo shell_exec("php artisan optimize:clear 2>&1") . "\n";
echo "</pre>";
