<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') || die();

call_user_func(static function (): void {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['aiscareer_rate'] ??= [
        'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
        'backend' => \TYPO3\CMS\Core\Cache\Backend\SimpleFileBackend::class,
        'options' => ['defaultLifetime' => 3600],
        'groups' => ['pages', 'all'],
    ];

    ExtensionUtility::configurePlugin(
        'AisCareer',
        'JobList',
        [
            Aistea\AisCareer\Controller\JobController::class => 'list',
        ],
        [
            Aistea\AisCareer\Controller\JobController::class => 'list',
        ]
    );

    ExtensionUtility::configurePlugin(
        'AisCareer',
        'JobDetail',
        [
            Aistea\AisCareer\Controller\JobController::class => 'show,apply,confirm,shareEvent',
        ],
        [
            Aistea\AisCareer\Controller\JobController::class => 'apply,confirm,shareEvent',
        ]
    );

    $iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
    $iconRegistry->registerIcon(
        'aiscareer-extension',
        SvgIconProvider::class,
        ['source' => 'EXT:ais_career/Resources/Public/Icons/Extension.svg']
    );
    $iconRegistry->registerIcon(
        'aiscareer-plugin-list',
        SvgIconProvider::class,
        ['source' => 'EXT:ais_career/Resources/Public/Icons/PluginList.svg']
    );
    $iconRegistry->registerIcon(
        'aiscareer-plugin-detail',
        SvgIconProvider::class,
        ['source' => 'EXT:ais_career/Resources/Public/Icons/PluginDetail.svg']
    );
    $iconRegistry->registerIcon(
        'aiscareer-record-job',
        SvgIconProvider::class,
        ['source' => 'EXT:ais_career/Resources/Public/Icons/RecordJob.svg']
    );
    $iconRegistry->registerIcon(
        'aiscareer-record-application',
        SvgIconProvider::class,
        ['source' => 'EXT:ais_career/Resources/Public/Icons/RecordApplication.svg']
    );
    $iconRegistry->registerIcon(
        'aiscareer-module-analytics',
        SvgIconProvider::class,
        ['source' => 'EXT:ais_career/Resources/Public/Icons/ModuleAnalytics.svg']
    );
});
