<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'TCA API Headless',
    'description' => 'Headless page delivery on top of TCA API: composed pages, typed content elements, navigation and SEO meta as JSON, described by OpenAPI.',
    'category' => 'fe',
    'author' => 'Maik Schneider',
    'author_email' => 'schneider.maik@me.com',
    'state' => 'alpha',
    'version' => '0.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
            'backend' => '13.4.0-14.99.99',
            'frontend' => '13.4.0-14.99.99',
            'tca_api' => '2.4.0-2.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
