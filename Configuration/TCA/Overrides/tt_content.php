<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

call_user_func(static function (): void {
    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist']['aiscareer_joblist'] = 'pi_flexform';
    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist']['aiscareer_jobdetail'] = 'pi_flexform';

    ExtensionManagementUtility::addPiFlexFormValue(
        'aiscareer_joblist',
        'FILE:EXT:ais_career/Configuration/FlexForms/JobList.xml'
    );
    ExtensionManagementUtility::addPiFlexFormValue(
        'aiscareer_jobdetail',
        'FILE:EXT:ais_career/Configuration/FlexForms/JobDetail.xml'
    );
});
