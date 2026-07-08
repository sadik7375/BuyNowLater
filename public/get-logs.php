<?php
header('Content-Type: text/plain');

$logFile = __DIR__ . '/../storage/logs/laravel.log';

if (!file_exists($logFile)) {
    echo "Log file does not exist at: " . $logFile;
    exit;
}

$lines = 100;
$data = array();
$fp = fopen($logFile, 'r');
if (!$fp) {
    echo "Unable to open log file.";
    exit;
}

// Quick way to read last N lines
fseek($fp, 0, SEEK_END);
$pos = ftell($fp);
$lineContent = '';

while ($pos > 0 && count($data) < $lines) {
    $pos--;
    fseek($fp, $pos);
    $char = fgetc($fp);
    if ($char === "\n") {
        if (!empty($lineContent)) {
            $data[] = strrev($lineContent);
            $lineContent = '';
        }
    } else {
        $lineContent .= $char;
    }
}
if (!empty($lineContent)) {
    $data[] = strrev($lineContent);
}
fclose($fp);

$data = array_reverse($data);
foreach ($data as $line) {
    echo $line . "\n";
}
