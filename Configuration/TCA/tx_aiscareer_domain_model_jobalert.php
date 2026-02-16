<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_jobalert',
        'label' => 'email',
        'label_alt' => 'country,department,contract_type',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
        'searchFields' => 'email,country,department,contract_type',
        'iconfile' => 'EXT:ais_career/Resources/Public/Icons/RecordJobAlert.svg',
    ],
    'types' => [
        '1' => [
            'showitem' => '
                --div--;General, email, country, department, contract_type, category, remote_possible, consent_privacy,
                --div--;Status, created_at, double_opt_in_confirmed_at, unsubscribed_at, last_sent_at,
                --div--;Tokens, double_opt_in_token, unsubscribe_token,
                --div--;Meta, source_url,
                --div--;Access, hidden
            ',
        ],
    ],
    'columns' => [
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
            ],
        ],
        'email' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_jobalert.email',
            'config' => [
                'type' => 'input',
                'eval' => 'trim,required,email',
            ],
        ],
        'country' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_jobalert.country',
            'config' => [
                'type' => 'input',
                'eval' => 'trim,upper',
            ],
        ],
        'department' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_jobalert.department',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
            ],
        ],
        'contract_type' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_jobalert.contract_type',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
            ],
        ],
        'category' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_jobalert.category',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'sys_category',
                'default' => 0,
                'items' => [
                    ['', 0],
                ],
            ],
        ],
        'remote_possible' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_jobalert.remote_possible',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => -1,
                'items' => [
                    ['LLL:EXT:ais_career/Resources/Private/Language/locallang.xlf:filter.option.any', -1],
                    ['LLL:EXT:ais_career/Resources/Private/Language/locallang.xlf:filter.option.onSite', 0],
                    ['LLL:EXT:ais_career/Resources/Private/Language/locallang.xlf:filter.option.remote', 1],
                ],
            ],
        ],
        'consent_privacy' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_jobalert.consent_privacy',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'source_url' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_jobalert.source_url',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
                'size' => 60,
            ],
        ],
        'created_at' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_jobalert.created_at',
            'config' => [
                'type' => 'datetime',
                'dbType' => 'int',
                'format' => 'datetime',
                'default' => 0,
                'readOnly' => true,
            ],
        ],
        'double_opt_in_token' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_jobalert.double_opt_in_token',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
                'readOnly' => true,
            ],
        ],
        'double_opt_in_confirmed_at' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_jobalert.double_opt_in_confirmed_at',
            'config' => [
                'type' => 'datetime',
                'dbType' => 'int',
                'format' => 'datetime',
                'default' => 0,
                'readOnly' => true,
            ],
        ],
        'unsubscribe_token' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_jobalert.unsubscribe_token',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
                'readOnly' => true,
            ],
        ],
        'unsubscribed_at' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_jobalert.unsubscribed_at',
            'config' => [
                'type' => 'datetime',
                'dbType' => 'int',
                'format' => 'datetime',
                'default' => 0,
                'readOnly' => true,
            ],
        ],
        'last_sent_at' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_jobalert.last_sent_at',
            'config' => [
                'type' => 'datetime',
                'dbType' => 'int',
                'format' => 'datetime',
                'default' => 0,
                'readOnly' => true,
            ],
        ],
    ],
];
