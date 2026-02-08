<?php

declare(strict_types=1);

return [
    'aiscareer' => [
        'parent' => 'web',
        'position' => ['after' => 'web_list'],
        'access' => 'user,group',
        'path' => '/module/web/aiscareer',
        'iconIdentifier' => 'aiscareer-extension',
        'labels' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang.xlf:module.aiscareer',
        'extensionName' => 'AisCareer',
        'controllerActions' => [
            \Aistea\AisCareer\Controller\Backend\AnalyticsController::class => [
                'index',
            ],
        ],
    ],
];
