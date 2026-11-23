<?php

declare(strict_types=1);

return [
    'general' => [
        'table' => 'pages',
        'resourceName' => 'headless_pages',
        'resourceType' => 'HeadlessPages',
        'operations' => ['show'],
        'recordTypes' => [1, 3, 4],
        'enabledSetting' => 'tca_api_headless.enabled',
    ],
    'columns' => [
        'title' => ['groups' => ['show']],
        'slug' => ['groups' => ['show']],
    ],
    'cache' => [
        'enabled' => false,
    ],
];
