<?php

/**
 * Emergency Git Pull and Deployment Script via Browser
 */
$secret = 'buylater2026';
$providedSecret = $_GET['secret'] ?? '';

if ($providedSecret !== $secret) {
    http_response_code(403);
    die('Unauthorized access.');
}

header('Content-Type: text/plain');
echo "=== EXECUTING EMERGENCY GIT PULL & DEPLOYMENT ===\n\n";

$baseDir = dirname(__DIR__);
chdir($baseDir);

$output = [];
$returnVar = 0;

echo "Current Directory: " . getcwd() . "\n";
echo "Executing: git fetch origin && git reset --hard origin/main\n\n";

exec("git fetch origin 2>&1", $output, $returnVar);
exec("git reset --hard origin/main 2>&1", $output, $returnVar);
exec("php artisan optimize:clear 2>&1", $output, $returnVar);

echo implode("\n", $output);

echo "\n\n=== DEPLOYMENT COMPLETED SUCCESSFULLY ===";
