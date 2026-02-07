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

    ExtensionManagementUtility::addPiFlexFormValue(
        'aiscareer_joblist',
        'FILE:EXT:ais_career/Configuration/FlexForms/JobList.xml'
    );
    ExtensionManagementUtility::addPiFlexFormValue(
        'aiscareer_jobdetail',
        'FILE:EXT:ais_career/Configuration/FlexForms/JobDetail.xml'
    );

    ExtensionManagementUtility::addStaticFile(
        'ais_career',
        'Configuration/TypoScript',
        'AIS Career'
    );

});
