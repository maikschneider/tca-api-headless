<?php

declare(strict_types=1);

use MaikSchneider\TcaApi\Serializer\FileProcessing\FileProcessor;
use MaikSchneider\TcaApi\Serializer\FileProcessing\ImageProcessor;

// Content is only read as part of a composed page, so it has no endpoint of its own.
return [
    'general' => [
        'table' => 'tt_content',
        'resourceName' => 'headless_content',
        'resourceType' => 'HeadlessContent',
        'operations' => [],
        'recordTypes' => [
            'include' => ['text', 'textmedia', 'image', 'header', 'bullets', 'table', 'uploads'],
            'typeColumns' => true,
        ],
        'enabledSetting' => 'tca_api_headless.enabled',
    ],
    'columns' => [
        'CType' => ['groups' => ['list', 'show']],
        'header' => ['groups' => ['list', 'show']],
        'bodytext' => ['groups' => ['list', 'show']],
        'image' => ['groups' => ['list', 'show'], 'processor' => ImageProcessor::class],
        'assets' => ['groups' => ['list', 'show'], 'processor' => FileProcessor::class],
        'media' => ['groups' => ['list', 'show'], 'processor' => FileProcessor::class],
    ],
    'cache' => [
        'enabled' => false,
    ],
];
