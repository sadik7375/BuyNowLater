<?php

$path = 'resources/views/dashboard/index.blade.php';
$content = file_get_contents($path);

// Replace garbled characters with correct UTF-8 representations
$replacements = [
    '≡ƒôè' => '📈',
    '≡ƒÆ░' => '💰',
    'ΓÅ░' => '⏰',
    '≡ƒöö' => '🔔',
    'ΓÜÖ∩╕Å' => '⚙️',
    '≡ƒÜ¿' => '🚨',
    'ΓÜá∩╕Å' => '⚠️',
    '≡ƒ¢ì∩╕Å' => '📝',
    '≡ƒÅ╖∩╕Å' => '🏷️',
    'ΓåÆ' => '→',
    'ΓÇö' => '—',
    '≡ƒÜÇ' => '🚀',
    'ΓÜí' => '⚡',
    'Γ£ô' => '✓',
    'Γ£ò' => '✗',
    'ΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉΓòÉ' => '───────────────',
    'ΓöÇΓöÇ' => '──'
];

foreach ($replacements as $garbled => $correct) {
    $content = str_replace($garbled, $correct, $content);
}

file_put_contents($path, $content);

echo "Emojis and characters fixed successfully!\n";
