<?php

declare(strict_types=1);



return [
    'ctrl' => [
        'title' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_application',
        'label' => 'email',
        'label_alt' => 'first_name,last_name',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'first_name,last_name,email,phone,message',
        'iconfile' => 'EXT:ais_career/Resources/Public/Icons/RecordApplication.svg',
    ],
    'types' => [
        '1' => [
            'showitem' => '
                --div--;General, job, first_name, last_name, email, phone, message, consent_privacy, created_at,
                --div--;Media, cv_file,
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
        'job' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_application.job',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_aiscareer_domain_model_job',
                'minitems' => 1,
                'maxitems' => 1,
            ],
        ],
        'first_name' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_application.first_name',
            'config' => [
                'type' => 'input',
                'eval' => 'trim,required',
            ],
        ],
        'last_name' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_application.last_name',
            'config' => [
                'type' => 'input',
                'eval' => 'trim,required',
            ],
        ],
        'email' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_application.email',
            'config' => [
                'type' => 'input',
                'eval' => 'trim,required,email',
            ],
        ],
        'phone' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_application.phone',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
            ],
        ],
        'message' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_application.message',
            'config' => [
                'type' => 'text',
                'eval' => 'trim',
                'cols' => 40,
                'rows' => 6,
            ],
        ],
        'cv_file' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_application.cv_file',
            'config' => [
                'type' => 'file',
                'allowed' => 'pdf,doc,docx',
                'maxitems' => 1,
                'appearance' => [
                    'createNewRelationLinkTitle' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.addFileReference',
                ],
            ],
        ],
        'consent_privacy' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_application.consent_privacy',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'created_at' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_application.created_at',
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
