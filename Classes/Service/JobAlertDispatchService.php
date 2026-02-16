<?php

declare(strict_types=1);

namespace Aistea\AisCareer\Service;

use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class JobAlertDispatchService
{
    private ?LoggerInterface $logger = null;

    public function dispatchForJobUid(int $jobUid): int
    {
        return (int)($this->dispatchForJobUidWithResult($jobUid)['sentCount'] ?? 0);
    }

    /** @return array{jobUid:int,sentCount:int,failedCount:int,subscriberCount:int,validRecipientCount:int,reason:string,senderSource:string} */
    public function dispatchForJobUidWithResult(int $jobUid): array
    {
        $result = [
            'jobUid' => $jobUid,
            'sentCount' => 0,
            'failedCount' => 0,
            'subscriberCount' => 0,
            'validRecipientCount' => 0,
            'reason' => 'unknown',
            'senderSource' => '',
        ];

        if ($jobUid <= 0) {
            $result['reason'] = 'invalid_job_uid';
            return $result;
        }

        $job = $this->findJobByUid($jobUid);
        if (!is_array($job)) {
            $this->logger()->info('Job alert dispatch skipped: job not triggerable', [
                'jobUid' => $jobUid,
            ]);
            $result['reason'] = 'job_not_triggerable';
            return $result;
        }

        ['email' => $fromEmail, 'name' => $fromName, 'source' => $senderSource] = $this->resolveSenderConfiguration((int)$job['pid']);
        $result['senderSource'] = $senderSource;
        if ($fromEmail === '' || !GeneralUtility::validEmail($fromEmail)) {
            $this->logger()->warning('Job alert dispatch skipped: invalid sender email configuration', [
                'jobUid' => $jobUid,
                'configuredFromEmail' => $fromEmail,
                'senderSource' => $senderSource,
            ]);
            $result['reason'] = 'invalid_sender';
            return $result;
        }

        $alertRows = $this->findAllConfirmedAlerts();
        $result['subscriberCount'] = count($alertRows);
        if ($alertRows === []) {
            $this->logger()->info('Job alert dispatch: no confirmed subscribers found', [
                'jobUid' => $jobUid,
            ]);
            $this->markJobAlertTriggered($jobUid);
            $result['reason'] = 'no_confirmed_subscribers';
            return $result;
        }

        $sent = 0;
        $failed = 0;
        $validRecipients = 0;
        foreach ($alertRows as $alert) {
            $email = strtolower(trim((string)($alert['email'] ?? '')));
            if ($email === '' || !GeneralUtility::validEmail($email)) {
                continue;
            }
            $validRecipients++;

            $sourceUrl = (string)($alert['source_url'] ?? '');
            $unsubscribeUrl = $this->buildUnsubscribeUrl(
                $sourceUrl,
                (string)($alert['unsubscribe_token'] ?? '')
            );
            $browseUrl = $this->resolveBrowseUrl((int)$job['pid'], $sourceUrl);
            $jobUrl = $this->buildJobUrl($sourceUrl, (string)($job['slug'] ?? ''));

            $view = GeneralUtility::makeInstance(StandaloneView::class);
            $view->setTemplatePathAndFilename('EXT:ais_career/Resources/Private/Templates/Email/JobAlertDigest.html');
            $view->assignMultiple([
                'jobs' => [[
                    'title' => (string)$job['title'],
                    'location' => (string)$job['location'],
                    'url' => $jobUrl,
                ]],
                'unsubscribeUrl' => $unsubscribeUrl,
                'browseUrl' => $browseUrl,
            ]);

            $mail = GeneralUtility::makeInstance(MailMessage::class);
            $mail->setFrom([$fromEmail => $fromName !== '' ? $fromName : 'AIS Career']);
            $mail->setTo([$email]);
            $mail->setSubject('New job alert: ' . (string)$job['title']);
            $mail->html($view->render());
            try {
                $mail->send();
                $sent++;
            } catch (\Throwable $exception) {
                $this->logger()->error('Job alert mail send failed', [
                    'jobUid' => $jobUid,
                    'alertUid' => (int)$alert['uid'],
                    'recipient' => $email,
                    'exception' => $exception,
                ]);
                $failed++;
                continue;
            }

            $this->markAlertLastSent((int)$alert['uid']);
        }

        $this->markJobAlertTriggered($jobUid);
        $this->logger()->info('Job alert dispatch finished', [
            'jobUid' => $jobUid,
            'sentCount' => $sent,
            'failedCount' => $failed,
            'subscriberCount' => count($alertRows),
            'validRecipientCount' => $validRecipients,
            'senderSource' => $senderSource,
        ]);

        $result['sentCount'] = $sent;
        $result['failedCount'] = $failed;
        $result['validRecipientCount'] = $validRecipients;
        $result['reason'] = $sent > 0
            ? 'sent'
            : ($validRecipients > 0 ? 'delivery_failed' : 'no_valid_recipients');

        return $result;
    }

    private function findJobByUid(int $jobUid): ?array
    {
        $nowTs = (int)(new \DateTime())->format('U');
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_aiscareer_domain_model_job');
        $qb->getRestrictions()->removeAll();

        $row = $qb
            ->select('uid', 'pid', 'title', 'slug', 'country', 'city', 'location_label')
            ->from('tx_aiscareer_domain_model_job')
            ->where(
                $qb->expr()->eq('uid', $qb->createNamedParameter($jobUid, ParameterType::INTEGER)),
                $qb->expr()->eq('trigger_job_alert_now', 1),
                $qb->expr()->eq('deleted', 0),
                $qb->expr()->eq('hidden', 0),
                $qb->expr()->eq('is_active', 1),
                $qb->expr()->or(
                    $qb->expr()->lte('published_from', $qb->createNamedParameter($nowTs, ParameterType::INTEGER)),
                    $qb->expr()->eq('published_from', 0)
                ),
                $qb->expr()->or(
                    $qb->expr()->gte('published_to', $qb->createNamedParameter($nowTs, ParameterType::INTEGER)),
                    $qb->expr()->eq('published_to', 0)
                )
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if (!is_array($row)) {
            return null;
        }

        $location = trim((string)($row['location_label'] ?? ''));
        if ($location === '') {
            $city = trim((string)($row['city'] ?? ''));
            $country = trim((string)($row['country'] ?? ''));
            $location = trim(implode(', ', array_filter([$city, $country])));
        }

        return [
            'uid' => (int)$row['uid'],
            'pid' => (int)$row['pid'],
            'title' => (string)$row['title'],
            'slug' => (string)$row['slug'],
            'location' => $location,
        ];
    }

    /** @return array{email:string,name:string,source:string} */
    private function resolveSenderConfiguration(int $jobPid): array
    {
        $fromPlugin = $this->resolveSenderFromJobAlertPlugin($jobPid);
        if (is_array($fromPlugin) && ($fromPlugin['email'] ?? '') !== '') {
            return [
                'email' => (string)$fromPlugin['email'],
                'name' => (string)($fromPlugin['name'] ?? 'AIS Career'),
                'source' => 'plugin_flexform',
            ];
        }

        return [
            'email' => trim((string)($GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] ?? '')),
            'name' => trim((string)($GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] ?? 'AIS Career')),
            'source' => 'typo3_mail_defaults',
        ];
    }

    /** @return array{email:string,name:string}|null */
    private function resolveSenderFromJobAlertPlugin(int $jobPid): ?array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');
        $qb->getRestrictions()->removeAll();

        $rows = $qb
            ->select('uid', 'pid', 'pi_flexform')
            ->from('tt_content')
            ->where(
                $qb->expr()->eq('CType', $qb->createNamedParameter('list')),
                $qb->expr()->eq('list_type', $qb->createNamedParameter('aiscareer_jobalert')),
                $qb->expr()->eq('deleted', 0),
                $qb->expr()->eq('hidden', 0)
            )
            ->orderBy('uid', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        if ($rows === []) {
            return null;
        }

        usort(
            $rows,
            static fn(array $a, array $b): int => ((int)$a['pid'] === $jobPid ? 0 : 1) <=> ((int)$b['pid'] === $jobPid ? 0 : 1)
        );

        foreach ($rows as $row) {
            $piFlexForm = (string)($row['pi_flexform'] ?? '');
            if ($piFlexForm === '') {
                continue;
            }

            $fromEmail = trim($this->extractFlexFormValue($piFlexForm, 'settings.jobAlertFromEmail'));
            if ($fromEmail === '' || !GeneralUtility::validEmail($fromEmail)) {
                continue;
            }

            return [
                'email' => $fromEmail,
                'name' => trim($this->extractFlexFormValue($piFlexForm, 'settings.jobAlertFromName') ?: 'AIS Career'),
            ];
        }

        return null;
    }

    private function extractFlexFormValue(string $xml, string $fieldName): string
    {
        if ($xml === '' || $fieldName === '') {
            return '';
        }

        libxml_use_internal_errors(true);
        $flexForm = simplexml_load_string($xml);
        if (!$flexForm instanceof \SimpleXMLElement) {
            return '';
        }

        $matches = $flexForm->xpath('//field[@index="' . $fieldName . '"]/value[@index="vDEF"]');
        if (!is_array($matches) || $matches === []) {
            return '';
        }

        return trim((string)$matches[0]);
    }

    /** @return array<int, array<string, mixed>> */
    private function findAllConfirmedAlerts(): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_aiscareer_domain_model_jobalert');
        $qb->getRestrictions()->removeAll();

        return $qb
            ->select('uid', 'email', 'unsubscribe_token', 'source_url')
            ->from('tx_aiscareer_domain_model_jobalert')
            ->where(
                $qb->expr()->eq('deleted', 0),
                $qb->expr()->eq('hidden', 0),
                $qb->expr()->gt('double_opt_in_confirmed_at', 0),
                $qb->expr()->eq('unsubscribed_at', 0)
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function markJobAlertTriggered(int $jobUid): void
    {
        $nowTs = (int)(new \DateTime())->format('U');
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_aiscareer_domain_model_job');
        $qb->getRestrictions()->removeAll();

        $qb->update('tx_aiscareer_domain_model_job')
            ->set('trigger_job_alert_now', $qb->createNamedParameter(0, ParameterType::INTEGER), false)
            ->set('alert_triggered_at', $qb->createNamedParameter($nowTs, ParameterType::INTEGER), false)
            ->set('tstamp', $qb->createNamedParameter($nowTs, ParameterType::INTEGER), false)
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($jobUid, ParameterType::INTEGER)))
            ->executeStatement();
    }

    private function markAlertLastSent(int $alertUid): void
    {
        if ($alertUid <= 0) {
            return;
        }

        $nowTs = (int)(new \DateTime())->format('U');
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_aiscareer_domain_model_jobalert');
        $qb->getRestrictions()->removeAll();

        $qb->update('tx_aiscareer_domain_model_jobalert')
            ->set('last_sent_at', $qb->createNamedParameter($nowTs, ParameterType::INTEGER), false)
            ->set('tstamp', $qb->createNamedParameter($nowTs, ParameterType::INTEGER), false)
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($alertUid, ParameterType::INTEGER)))
            ->executeStatement();
    }

    private function buildUnsubscribeUrl(string $sourceUrl, string $token): string
    {
        if ($sourceUrl === '' || $token === '') {
            return '';
        }

        return $this->appendQuery($this->stripQuery($sourceUrl), [
            'tx_aiscareer_jobalert[action]' => 'unsubscribe',
            'tx_aiscareer_jobalert[controller]' => 'Alert',
            'tx_aiscareer_jobalert[token]' => $token,
        ]);
    }

    private function resolveBrowseUrl(int $jobPid, string $sourceUrl): string
    {
        $listPid = $this->resolveListPidFromJobAlertPlugin($jobPid);
        if ($listPid <= 0) {
            return $this->stripQuery($sourceUrl);
        }

        $slug = $this->findPageSlugByUid($listPid);
        if ($slug === '') {
            return $this->stripQuery($sourceUrl);
        }

        $origin = $this->extractOrigin($sourceUrl);
        $path = '/' . ltrim($slug, '/');
        if ($origin === '') {
            return $path;
        }

        return rtrim($origin, '/') . $path;
    }

    private function resolveListPidFromJobAlertPlugin(int $jobPid): int
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');
        $qb->getRestrictions()->removeAll();

        $rows = $qb
            ->select('uid', 'pid', 'pi_flexform')
            ->from('tt_content')
            ->where(
                $qb->expr()->eq('CType', $qb->createNamedParameter('list')),
                $qb->expr()->eq('list_type', $qb->createNamedParameter('aiscareer_jobalert')),
                $qb->expr()->eq('deleted', 0),
                $qb->expr()->eq('hidden', 0)
            )
            ->orderBy('uid', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        if ($rows === []) {
            return 0;
        }

        usort(
            $rows,
            static fn(array $a, array $b): int => ((int)$a['pid'] === $jobPid ? 0 : 1) <=> ((int)$b['pid'] === $jobPid ? 0 : 1)
        );

        foreach ($rows as $row) {
            $piFlexForm = (string)($row['pi_flexform'] ?? '');
            if ($piFlexForm === '') {
                continue;
            }

            $listPidRaw = trim($this->extractFlexFormValue($piFlexForm, 'settings.listPid'));
            if ($listPidRaw === '') {
                continue;
            }

            // FlexForm group fields are often stored as "pages_123".
            if (str_contains($listPidRaw, '_')) {
                $parts = explode('_', $listPidRaw);
                $listPidRaw = (string)end($parts);
            }

            $listPid = (int)$listPidRaw;
            if ($listPid > 0) {
                return $listPid;
            }
        }

        return 0;
    }

    private function findPageSlugByUid(int $pageUid): string
    {
        if ($pageUid <= 0) {
            return '';
        }

        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');
        $qb->getRestrictions()->removeAll();

        $row = $qb
            ->select('slug')
            ->from('pages')
            ->where(
                $qb->expr()->eq('uid', $qb->createNamedParameter($pageUid, ParameterType::INTEGER)),
                $qb->expr()->eq('deleted', 0)
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? trim((string)($row['slug'] ?? '')) : '';
    }

    private function extractOrigin(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . (int)$parts['port'];
        }

        return $origin;
    }

    private function buildJobUrl(string $sourceUrl, string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return $this->stripQuery($sourceUrl);
        }

        $parts = parse_url($sourceUrl);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $base = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $base .= ':' . (int)$parts['port'];
        }

        return rtrim($base, '/') . '/jobs/' . rawurlencode($slug);
    }

    private function stripQuery(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $result = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $result .= ':' . (int)$parts['port'];
        }
        $result .= $parts['path'] ?? '';

        return $result;
    }

    /** @param array<string, string> $params */
    private function appendQuery(string $url, array $params): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return $url;
        }

        $query = [];
        if (isset($parts['query'])) {
            parse_str((string)$parts['query'], $query);
        }
        foreach ($params as $key => $value) {
            $query[$key] = $value;
        }

        $rebuilt = '';
        if (isset($parts['scheme'])) {
            $rebuilt .= $parts['scheme'] . '://';
        }
        if (isset($parts['host'])) {
            $rebuilt .= $parts['host'];
        }
        if (isset($parts['port'])) {
            $rebuilt .= ':' . (int)$parts['port'];
        }
        $rebuilt .= $parts['path'] ?? '';
        $rebuilt .= '?' . http_build_query($query);

        return $rebuilt;
    }

    private function logger(): LoggerInterface
    {
        if ($this->logger === null) {
            $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(self::class);
        }
        return $this->logger;
    }
}
