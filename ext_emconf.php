<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Headless Pages',
    'description' => 'Composes editor-built TYPO3 pages into a clean Portable Text + schema.org JSON contract for headless frontends.',
    'category' => 'misc',
    'author' => 'Maik Schneider',
    'author_email' => 'schneider.maik@me.com',
    'state' => 'alpha',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
            'frontend' => '',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
