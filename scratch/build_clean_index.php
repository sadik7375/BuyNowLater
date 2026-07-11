<?php

$content = file_get_contents('scratch/index_sidebar_utf8.blade.php');

// 1. Remove "Price Plan" and "How It Works" buttons from sidebar navigation
$content = preg_replace(
    '/<button class="sidebar-btn"[^>]*onclick="switchTab\(event, \'tab-price-plan\'\)"[^>]*>.*?<\/button>\s*/s',
    '',
    $content
);

$content = preg_replace(
    '/<button class="sidebar-btn"[^>]*onclick="switchTab\(event, \'tab-how-it-works\'\)"[^>]*>.*?<\/button>\s*/s',
    '',
    $content
);

// Clean up dividers in the sidebar nav block
$content = preg_replace(
    '/<hr class="sidebar-divider">\s*<div class="sidebar-section-label">App<\/div>\s*<hr class="sidebar-divider">\s*(?=<button class="sidebar-btn" onclick="switchTab\(event, \'tab-settings\'\)">)/s',
    '<hr class="sidebar-divider">' . "\n        " . '<div class="sidebar-section-label">App</div>' . "\n        ",
    $content
);

// 2. Remove Price Plan and How It Works from hidden tab buttons for JS compatibility
$content = preg_replace(
    '/<button class="tab-button"[^>]*onclick="switchTab\(event, \'tab-price-plan\'\)"[^>]*>.*?<\/button>\s*/s',
    '',
    $content
);

$content = preg_replace(
    '/<button class="tab-button"[^>]*onclick="switchTab\(event, \'tab-how-it-works\'\)"[^>]*>.*?<\/button>\s*/s',
    '',
    $content
);

// 3. Remove the date filter toolbar
$content = preg_replace(
    '/<div class="filter-toolbar-container">.*?<\/form>\s*<\/div>\s*/s',
    '',
    $content
);

// 4. Remove the stats cards grid
$content = preg_replace(
    '/<!-- 4 Stats Cards Grid.*?<\/div>\s*(?=<!-- Hidden tab buttons)/s',
    '',
    $content
);

// 5. Remove Tab 6 (Price Plan) and Tab 7 (How It Works) content divs
$content = preg_replace(
    '/<!-- Tab 6: Price Plan -->.*?<!-- Tab 7: How It Works & Benefits -->.*?<\/div>\s*<\/div>\s*(?=<\/div><!-- \/\.dashboard-container -->)/s',
    '',
    $content
);

file_put_contents('resources/views/dashboard/index.blade.php', $content);

echo "Cleaned dashboard index file generated successfully via PHP!\n";
