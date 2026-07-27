<?php

$baseDir = dirname(__DIR__);
chdir($baseDir);

if (file_exists($baseDir . '/.git/index.lock')) {
    @unlink($baseDir . '/.git/index.lock');
}

header('Content-Type: text/plain');

echo "Syncing repository...\n";
echo "Directory: " . getcwd() . "\n\n";

echo "=== GIT REMOTE SET-URL ===\n";
echo shell_exec("git remote set-url origin https://github.com/sadik7375/BuyNowLater.git 2>&1") . "\n\n";

echo "=== GIT FETCH ===\n";
echo shell_exec("git fetch --all 2>&1") . "\n\n";

echo "=== GIT RESET ===\n";
echo shell_exec("git reset --hard origin/main 2>&1") . "\n\n";

echo "=== ARTISAN CLEAR ===\n";
echo shell_exec("php artisan optimize:clear 2>&1") . "\n\n";

echo "SUCCESSFULLY SYNCED TO LATEST COMMIT!\n";
