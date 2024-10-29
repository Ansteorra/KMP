<?php

return [
    'DebugKit' => [
        'onlyDebug' => true,
    ],
    'Bake' => [
        'onlyCli' => true,
        'optional' => true,
    ],
    'Migrations' => [
        'onlyCli' => true,
    ],
    'BootstrapUI' => [],
    'Bootstrap' => [],
    'Muffin/Footprint' => [],
    'Authentication' => [],
    'Authorization' => [],
    'Muffin/Trash' => [],
    'ADmad/Glide' => [],
    'GitHubIssueSubmitter' => [],
    'Activities' => [
        'migrationOrder' => 1,
    ],
    'Officers' => [
        'migrationOrder' => 2,
    ],
    'AssetMix' => [],
    'Awards' => [
        'migrationOrder' => 3,
    ],
    'CsvView' => [],
];