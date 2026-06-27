<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'TCA API Headless',
    'description' => 'Headless JSON delivery for TYPO3 — composes pages into a clean Portable Text contract, built on tca-api.',
    'category' => 'misc',
    'author' => 'Maik Schneider',
    'author_email' => 'schneider.maik@me.com',
    'state' => 'alpha',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
            'tca_api' => '0.4.0-0.99.99',
            'frontend' => '',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
