<?php

$plugins = [
    'DebugKit' => [
        'onlyDebug' => true,
        'optional' => true,
    ],
    'Bake' => [
        'onlyCli' => true,
        'optional' => true,
    ],
    'Tools' => [],
    'Migrations' => [
        'onlyCli' => true,
    ],
    'Muffin/Footprint' => [],
    'Muffin/Trash' => [],
    'BootstrapUI' => [],
    'Bootstrap' => [],
    'Authentication' => [],
    'Authorization' => [],
    'ADmad/Glide' => [],
    'Queue' => [],
    'CsvView' => [],
    'GitHubIssueSubmitter' => [],
    'Activities' => [
        'migrationOrder' => 1,
    ],
    'Officers' => [
        'migrationOrder' => 2,
    ],
    'Awards' => [
        'migrationOrder' => 3,
    ],
    'Waivers' => [
        'migrationOrder' => 4,
    ],
    //'Events' => [
    //    'migrationOrder' => 5,
    //],
    //'Template' => [
    //    'migrationOrder' => 6,
    //],
];

if (($_SERVER['HTTP_X_KMP_E2E'] ?? '') === '1') {
    unset($plugins['DebugKit']);
}

return $plugins;
