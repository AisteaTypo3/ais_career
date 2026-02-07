<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'AIS Career',
    'description' => 'Jobs listing and application for TYPO3 v13',
    'category' => 'plugin',
    'author' => 'Aistea',
    'author_email' => 'info@aistea.example',
    'state' => 'stable',
    'clearCacheOnLoad' => 1,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-13.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
