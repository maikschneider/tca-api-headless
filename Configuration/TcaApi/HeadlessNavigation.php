<?php

declare(strict_types=1);

return [
    'general' => [
        'table' => 'pages',
        'resourceName' => 'headless_navigation',
        'resourceType' => 'HeadlessNavigation',
        'operations' => ['show'],
        'recordTypes' => [1, 3, 4],
        'enabledSetting' => 'tca_api_headless.enabled',
    ],
    'columns' => [
        'title' => ['groups' => ['show']],
        'nav_title' => ['groups' => ['show']],
        'slug' => ['groups' => ['show']],
        'doktype' => ['groups' => ['show']],
    ],
    'cache' => [
        'enabled' => false,
        // The cache key does not vary by depth: a tree for a requested depth is never cached.
        'parametersToIgnore' => ['depth'],
    ],
];
