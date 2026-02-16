<?php

declare(strict_types=1);

namespace Aistea\AisCareer\Hooks;

use Aistea\AisCareer\Service\JobAlertDispatchService;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class JobDataHandlerHook
{
    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        string|int $id,
        array $fieldArray,
        DataHandler $dataHandler
    ): void {
        if ($table !== 'tx_aiscareer_domain_model_job') {
            return;
        }

        $jobUid = 0;
        if ($status === 'new') {
            $jobUid = (int)($dataHandler->substNEWwithIDs[(string)$id] ?? 0);
        } else {
            $jobUid = (int)$id;
        }

        if ($jobUid <= 0) {
            return;
        }

        $result = GeneralUtility::makeInstance(JobAlertDispatchService::class)->dispatchForJobUidWithResult($jobUid);
        $this->addBackendFlashMessage($result, $jobUid);
    }

    private function addBackendFlashMessage(array $result, int $jobUid): void
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($request === null) {
            return;
        }
        if (!ApplicationType::fromRequest($request)->isBackend()) {
            return;
        }

        $sentCount = (int)($result['sentCount'] ?? 0);
        $subscriberCount = (int)($result['subscriberCount'] ?? 0);
        $validRecipientCount = (int)($result['validRecipientCount'] ?? 0);
        $failedCount = (int)($result['failedCount'] ?? 0);
        $reason = (string)($result['reason'] ?? 'unknown');

        if ($sentCount > 0) {
            $message = sprintf(
                'Job alert sent: %d email(s) for job #%d (subscribers: %d, failed: %d).',
                $sentCount,
                $jobUid,
                $subscriberCount,
                $failedCount
            );
            $severity = ContextualFeedbackSeverity::OK;
        } else {
            $message = match ($reason) {
                'invalid_sender' => sprintf(
                    'Job alert for job #%d not sent: sender email is missing/invalid. Set JobAlert plugin "From email" or TYPO3 MAIL.defaultMailFromAddress.',
                    $jobUid
                ),
                'no_confirmed_subscribers' => sprintf(
                    'Job alert for job #%d not sent: no confirmed active subscribers found.',
                    $jobUid
                ),
                'no_valid_recipients' => sprintf(
                    'Job alert for job #%d not sent: %d subscriber(s) found, but no valid recipient email.',
                    $jobUid,
                    $subscriberCount
                ),
                'delivery_failed' => sprintf(
                    'Job alert for job #%d not sent: delivery failed for %d valid recipient(s). Check Admin Tools > Log.',
                    $jobUid,
                    $validRecipientCount
                ),
                'job_not_triggerable' => sprintf(
                    'Job alert for job #%d skipped: job is not triggerable (inactive/hidden/unpublished or trigger already reset).',
                    $jobUid
                ),
                default => sprintf(
                    'Job alert trigger processed for job #%d, but no emails were sent. Check Admin Tools > Log.',
                    $jobUid
                ),
            };
            $severity = ContextualFeedbackSeverity::WARNING;
        }

        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $message,
            'Job Alert',
            $severity,
            true
        );

        $queue = GeneralUtility::makeInstance(FlashMessageService::class)
            ->getMessageQueueByIdentifier('core.template.flashMessages');
        $queue->addMessage($flashMessage);
    }
}
