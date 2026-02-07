<?php

declare(strict_types=1);



return [
    'ctrl' => [
        'title' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job',
        'label' => 'title',
        'label_alt' => 'reference,location_label',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'sortby' => 'sorting',
        'default_sortby' => 'ORDER BY sorting',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'title,reference,description,location_label,department,contract_type,country,city',
        'iconfile' => 'EXT:ais_career/Resources/Public/Icons/RecordJob.svg',
    ],
    'types' => [
        '1' => [
            'showitem' => '
                --div--;General, title, reference, slug, is_active, categories,
                --div--;Location, country, city, location_label, department, contract_type, remote_possible,
                --div--;Publishing, employment_start, published_from, published_to,
                --div--;Content, description, responsibilities, qualifications, benefits,
                --div--;Media, attachments, contact_email,
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
        'title' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.title',
            'config' => [
                'type' => 'input',
                'eval' => 'trim,required',
            ],
        ],
        'reference' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.reference',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
            ],
        ],
        'slug' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.slug',
            'config' => [
                'type' => 'slug',
                'size' => 50,
                'eval' => 'uniqueInSite,required',
                'generatorOptions' => [
                    'fields' => ['title', 'reference'],
                    'fieldSeparator' => '-',
                    'replacements' => [
                        '/' => '-',
                    ],
                ],
                'fallbackCharacter' => '-',
                'default' => '',
            ],
        ],
        'description' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.description',
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
                'eval' => 'trim',
                'cols' => 40,
                'rows' => 10,
            ],
        ],
        'responsibilities' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.responsibilities',
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
                'eval' => 'trim',
                'cols' => 40,
                'rows' => 8,
            ],
        ],
        'qualifications' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.qualifications',
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
                'eval' => 'trim',
                'cols' => 40,
                'rows' => 8,
            ],
        ],
        'benefits' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.benefits',
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
                'eval' => 'trim',
                'cols' => 40,
                'rows' => 8,
            ],
        ],
        'country' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.country',
            'config' => [
                'type' => 'input',
                'eval' => 'trim,upper',
            ],
        ],
        'city' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.city',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
            ],
        ],
        'location_label' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.location_label',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
            ],
        ],
        'department' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.department',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
            ],
        ],
        'contract_type' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.contract_type',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
            ],
        ],
        'remote_possible' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.remote_possible',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'employment_start' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.employment_start',
            'config' => [
                'type' => 'datetime',
                'dbType' => 'int',
                'format' => 'date',
                'default' => 0,
            ],
        ],
        'published_from' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.published_from',
            'config' => [
                'type' => 'datetime',
                'dbType' => 'int',
                'format' => 'datetime',
                'default' => 0,
            ],
        ],
        'published_to' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.published_to',
            'config' => [
                'type' => 'datetime',
                'dbType' => 'int',
                'format' => 'datetime',
                'default' => 0,
            ],
        ],
        'is_active' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.is_active',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 1,
            ],
        ],
        'sorting' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'categories' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.categories',
            'config' => [
                'type' => 'category',
                'treeConfig' => [
                    'parentField' => 'parent',
                    'appearance' => [
                        'expandAll' => true,
                        'showHeader' => true,
                    ],
                ],
            ],
        ],
        'attachments' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.attachments',
            'config' => [
                'type' => 'file',
                'allowed' => '*',
                'maxitems' => 10,
                'appearance' => [
                    'createNewRelationLinkTitle' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.addFileReference',
                ],
            ],
        ],
        'contact_email' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.contact_email',
            'config' => [
                'type' => 'input',
                'eval' => 'trim,email',
            ],
        ],
    ],
];
