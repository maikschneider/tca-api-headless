<?php

declare(strict_types=1);

return [
    'general' => [
        'table' => 'pages',
        'resourceName' => 'pages',
        'resourceType' => 'Pages',
    ],
    'columns' => [
        'title' => ['groups' => ['list', 'show']],
        'slug' => ['groups' => ['list', 'show']],
        'doktype' => ['groups' => ['show']],
    ],
];
