<?php

declare(strict_types=1);



return [
    'ctrl' => [
        'title' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job',
        'label' => 'title',
        'label_alt' => 'reference,location_label',
        'label_alt_force' => true,
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'translationSource' => 'l10n_source',
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
                --div--;General, sys_language_uid, l10n_parent, l10n_source, title, reference, slug, is_active, categories,
                --div--;Location, country, city, location_label, department, contract_type, salary_min, salary_max, salary_currency, salary_period, remote_possible,
                --div--;Publishing, employment_start, published_from, published_to,
                --div--;Content, description, responsibilities, qualifications, benefits,
                --div--;Media, attachments,
                --div--;Contact, contact_name, contact_title, contact_phone, contact_public_email, contact_email, contact_image,
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
        'sys_language_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'special' => 'languages',
                'items' => [
                    ['LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.allLanguages', -1],
                    ['LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.default_value', 0],
                ],
                'default' => 0,
            ],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => 0,
                'items' => [
                    ['', 0],
                ],
                'foreign_table' => 'tx_aiscareer_domain_model_job',
                'foreign_table_where' => 'AND {#tx_aiscareer_domain_model_job}.{#pid}=###CURRENT_PID### AND {#tx_aiscareer_domain_model_job}.{#sys_language_uid} IN (-1,0)',
            ],
        ],
        'l10n_source' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_source',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => 0,
                'items' => [
                    ['', 0],
                ],
                'foreign_table' => 'tx_aiscareer_domain_model_job',
                'foreign_table_where' => 'AND {#tx_aiscareer_domain_model_job}.{#pid}=###CURRENT_PID### AND {#tx_aiscareer_domain_model_job}.{#sys_language_uid} IN (-1,0)',
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
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
                'eval' => 'uniqueInPid,required',
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
        'salary_min' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.salary_min',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'default' => 0,
                'range' => [
                    'lower' => 0,
                ],
            ],
        ],
        'salary_max' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.salary_max',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'default' => 0,
                'range' => [
                    'lower' => 0,
                ],
            ],
        ],
        'salary_currency' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.salary_currency',
            'config' => [
                'type' => 'input',
                'eval' => 'trim,upper',
                'size' => 8,
                'max' => 8,
                'default' => 'EUR',
            ],
        ],
        'salary_period' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.salary_period',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => 'year',
                'items' => [
                    ['LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.salary_period.hour', 'hour'],
                    ['LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.salary_period.day', 'day'],
                    ['LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.salary_period.week', 'week'],
                    ['LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.salary_period.month', 'month'],
                    ['LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.salary_period.year', 'year'],
                ],
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
                'allowed' => 'pdf,doc,docx,jpg,jpeg,png,webp,svg,gif',
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
        'contact_name' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.contact_name',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
            ],
        ],
        'contact_title' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.contact_title',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
            ],
        ],
        'contact_phone' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.contact_phone',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
            ],
        ],
        'contact_public_email' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.contact_public_email',
            'config' => [
                'type' => 'input',
                'eval' => 'trim,email',
            ],
        ],
        'contact_image' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ais_career/Resources/Private/Language/locallang_db.xlf:tx_aiscareer_domain_model_job.contact_image',
            'config' => [
                'type' => 'file',
                'allowed' => 'jpg,jpeg,png,webp,svg,gif',
                'maxitems' => 1,
                'appearance' => [
                    'createNewRelationLinkTitle' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.addFileReference',
                ],
            ],
        ],
    ],
];
