<?php

declare(strict_types=1);

return [
    'frontend' => [
        'maikschneider/headless-pages/page-content' => [
            'target' => \MaikSchneider\HeadlessPages\Http\PageContentMiddleware::class,
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
            'after' => [
                'typo3/cms-frontend/site',
            ],
        ],
    ],
];
