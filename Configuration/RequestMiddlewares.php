<?php

declare(strict_types=1);

return [
    'frontend' => [
        'maikschneider/tca-api-headless/page-content' => [
            'target' => \MaikSchneider\TcaApiHeadless\Http\PageContentMiddleware::class,
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
            'after' => [
                'typo3/cms-frontend/site',
            ],
        ],
    ],
];
