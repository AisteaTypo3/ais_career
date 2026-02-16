<?php

declare(strict_types=1);

defined('TYPO3') || die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

call_user_func(static function (): void {
    ExtensionUtility::registerPlugin(
        'AisCareer',
        'JobList',
        'AIS Career: Job List',
        'aiscareer-plugin-list'
    );
    ExtensionUtility::registerPlugin(
        'AisCareer',
        'JobDetail',
        'AIS Career: Job Detail',
        'aiscareer-plugin-detail'
    );
    ExtensionUtility::registerPlugin(
        'AisCareer',
        'JobAlert',
        'AIS Career: Job Alert',
        'aiscareer-plugin-list'
    );

    ExtensionManagementUtility::addStaticFile(
        'ais_career',
        'Configuration/TypoScript',
        'AIS Career'
    );

});
