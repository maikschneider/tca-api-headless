<?php

declare(strict_types=1);

use MaikSchneider\TcaApi\Serializer\FileProcessing\ImageProcessor;

return [
    'general' => [
        'table' => 'pages',
        'resourceName' => 'headless_pages',
        'resourceType' => 'HeadlessPages',
        // "list" is the lookup of one page by its path, see HeadlessPageHandler.
        'operations' => ['list', 'show'],
        'recordTypes' => [1, 3, 4],
        'enabledSetting' => 'tca_api_headless.enabled',
    ],
    'columns' => [
        'title' => ['groups' => ['show']],
        'slug' => ['groups' => ['show']],
        'seo_title' => ['groups' => ['show']],
        'description' => ['groups' => ['show']],
        'no_index' => ['groups' => ['show']],
        'no_follow' => ['groups' => ['show']],
        'canonical_link' => ['groups' => ['show']],
        'og_title' => ['groups' => ['show']],
        'og_description' => ['groups' => ['show']],
        // Like EXT:seo's og:image: the "social" crop, at most 2000 px wide.
        'og_image' => [
            'groups' => ['show'],
            'processor' => ImageProcessor::class,
            'image' => ['cropVariant' => 'social', 'maxWidth' => 2000, 'absolute' => true],
        ],
    ],
    'cache' => [
        'enabled' => false,
    ],
];
