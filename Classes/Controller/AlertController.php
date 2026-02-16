<?php

declare(strict_types=1);

namespace Aistea\AisCareer\Controller;

use Aistea\AisCareer\Domain\Repository\JobAlertRepository;
use Aistea\AisCareer\Domain\Repository\JobRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class AlertController extends ActionController
{
    public function __construct(
        protected readonly JobAlertRepository $jobAlertRepository,
        protected readonly JobRepository $jobRepository
    ) {
    }

    public function formAction(): ResponseInterface
    {
        $alertState = $this->request->hasArgument('alertState') ? (string)$this->request->getArgument('alertState') : '';
        $filterOptions = $this->collectFilterOptions();

        $this->view->assignMultiple([
            'settings' => $this->settings,
            'alertState' => $alertState,
            'filterOptions' => $filterOptions,
            'jobAlert' => [
                'country' => '',
                'department' => '',
                'contractType' => '',
                'remotePossible' => '',
                'category' => '',
                'email' => '',
            ],
            'formPagePid' => $this->resolveAlertPagePid(),
        ]);

        return $this->htmlResponse();
    }

    public function subscribeAction(): ResponseInterface
    {
        $alertData = $this->extractAlertData();
        $antiBot = $this->extractAntiBotData();
        $filters = $this->normalizeFilters($alertData);
        $email = strtolower(trim((string)($alertData['email'] ?? '')));
        $consent = $this->toBool($alertData['consentPrivacy'] ?? false);

        if ($email === '' || !GeneralUtility::validEmail($email)) {
            return $this->redirectToForm('invalid');
        }
        if (!$consent) {
            return $this->redirectToForm('consent');
        }
        $botError = $this->validateBotSignals($antiBot);
        if ($botError !== '') {
            return $this->redirectToForm($botError);
        }

        $now = (int)(new \DateTime())->format('U');
        $token = $this->generateToken();
        $unsubscribeToken = $this->generateToken();
        $sourceUrl = $this->resolveAlertPageUrl();

        $existing = $this->jobAlertRepository->findOneByEmailAndFilters($email, $filters);
        if (is_array($existing) && (int)($existing['unsubscribed_at'] ?? 0) === 0 && (int)($existing['double_opt_in_confirmed_at'] ?? 0) > 0) {
            return $this->redirectToForm('exists');
        }

        $storagePid = $this->resolveStoragePid();

        if (is_array($existing)) {
            $uid = (int)$existing['uid'];
            $this->jobAlertRepository->updateByUid($uid, [
                'pid' => $storagePid,
                'email' => $email,
                'country' => (string)$filters['country'],
                'department' => (string)$filters['department'],
                'contract_type' => (string)$filters['contractType'],
                'category' => (int)$filters['category'],
                'remote_possible' => (int)$filters['remotePossible'],
                'consent_privacy' => 1,
                'source_url' => $sourceUrl,
                'double_opt_in_token' => $token,
                'double_opt_in_confirmed_at' => 0,
                'unsubscribe_token' => $unsubscribeToken,
                'unsubscribed_at' => 0,
                'hidden' => 0,
                'tstamp' => $now,
            ]);
        } else {
            $this->jobAlertRepository->create([
                'pid' => $storagePid,
                'email' => $email,
                'country' => (string)$filters['country'],
                'department' => (string)$filters['department'],
                'contract_type' => (string)$filters['contractType'],
                'category' => (int)$filters['category'],
                'remote_possible' => (int)$filters['remotePossible'],
                'consent_privacy' => 1,
                'source_url' => $sourceUrl,
                'created_at' => $now,
                'double_opt_in_token' => $token,
                'double_opt_in_confirmed_at' => 0,
                'unsubscribe_token' => $unsubscribeToken,
                'unsubscribed_at' => 0,
                'last_sent_at' => 0,
                'hidden' => 0,
                'deleted' => 0,
                'crdate' => $now,
                'tstamp' => $now,
                'cruser_id' => 0,
            ]);
        }

        $this->sendOptInMail($email, $filters, $token, $unsubscribeToken);

        return $this->redirectToForm('pending');
    }

    public function confirmAction(string $token = ''): ResponseInterface
    {
        if ($token === '') {
            return $this->redirectToForm('invalid');
        }

        $alert = $this->jobAlertRepository->findOneByDoubleOptInToken($token);
        if (!is_array($alert)) {
            return $this->redirectToForm('invalid');
        }

        $uid = (int)$alert['uid'];
        $now = (int)(new \DateTime())->format('U');
        $this->jobAlertRepository->updateByUid($uid, [
            'double_opt_in_confirmed_at' => $now,
            'double_opt_in_token' => '',
            'unsubscribed_at' => 0,
            'hidden' => 0,
            'tstamp' => $now,
        ]);

        return $this->redirectToForm('confirmed');
    }

    public function unsubscribeAction(string $token = ''): ResponseInterface
    {
        if ($token === '') {
            return $this->redirectToForm('invalid');
        }

        $alert = $this->jobAlertRepository->findOneByUnsubscribeToken($token);
        if (!is_array($alert)) {
            return $this->redirectToForm('invalid');
        }

        $uid = (int)$alert['uid'];
        $now = (int)(new \DateTime())->format('U');
        $this->jobAlertRepository->updateByUid($uid, [
            'unsubscribed_at' => $now,
            'double_opt_in_token' => '',
            'hidden' => 1,
            'tstamp' => $now,
        ]);

        return $this->redirectToForm('unsubscribed');
    }

    private function collectFilterOptions(): array
    {
        $options = [
            'countries' => [],
            'departments' => [],
            'contractTypes' => [],
            'categories' => [],
            'remotePossibleAvailable' => false,
        ];

        $remoteCounts = ['yes' => 0, 'no' => 0];
        foreach ($this->jobRepository->findActive() as $job) {
            $country = strtoupper(trim($job->getCountry()));
            if ($country !== '') {
                $options['countries'][$country] = $this->getCountryLabel($country);
            }
            $department = trim($job->getDepartment());
            if ($department !== '') {
                $options['departments'][$department] = $department;
            }
            $contractType = trim($job->getContractType());
            if ($contractType !== '') {
                $options['contractTypes'][$contractType] = $contractType;
            }
            if ($job->isRemotePossible()) {
                $remoteCounts['yes']++;
            } else {
                $remoteCounts['no']++;
            }
            foreach ($job->getCategories() as $category) {
                $options['categories'][$category->getUid()] = $category->getTitle();
            }
        }

        ksort($options['countries']);
        ksort($options['departments']);
        ksort($options['contractTypes']);
        asort($options['categories']);
        $options['remotePossibleAvailable'] = ($remoteCounts['yes'] + $remoteCounts['no']) > 0
            && ($remoteCounts['yes'] > 0 || $remoteCounts['no'] > 0);

        return $options;
    }

    private function getCountryLabel(string $countryCode): string
    {
        if (!class_exists(\Locale::class)) {
            return $countryCode;
        }
        $label = \Locale::getDisplayRegion('-' . $countryCode, \Locale::getDefault());
        return $label !== '' ? $label : $countryCode;
    }

    private function normalizeFilters(array $alertData): array
    {
        $remote = -1;
        if (array_key_exists('remotePossible', $alertData) && $alertData['remotePossible'] !== '' && $alertData['remotePossible'] !== null) {
            $remote = (int)$alertData['remotePossible'] > 0 ? 1 : 0;
        }

        return [
            'country' => strtoupper(trim((string)($alertData['country'] ?? ''))),
            'department' => trim((string)($alertData['department'] ?? '')),
            'contractType' => trim((string)($alertData['contractType'] ?? '')),
            'category' => max(0, (int)($alertData['category'] ?? 0)),
            'remotePossible' => $remote,
        ];
    }

    private function validateBotSignals(array $antiBot): string
    {
        $honeypot = trim((string)($antiBot['website'] ?? ''));
        if ($honeypot !== '') {
            return 'bot';
        }

        $minSeconds = (int)($this->settings['jobAlertBotMinSeconds'] ?? ($this->settings['botMinSeconds'] ?? 3));
        $maxSeconds = (int)($this->settings['jobAlertBotMaxSeconds'] ?? ($this->settings['botMaxSeconds'] ?? 86400));
        $ts = (int)($antiBot['ts'] ?? 0);
        $now = (int)(new \DateTime())->format('U');
        if ($ts > 0) {
            $age = $now - $ts;
            if (($minSeconds > 0 && $age < $minSeconds) || ($maxSeconds > 0 && $age > $maxSeconds)) {
                return 'bot';
            }
        }

        if (!empty($this->settings['jobAlertRequireHeaders'])) {
            $userAgent = trim((string)GeneralUtility::getIndpEnv('HTTP_USER_AGENT'));
            $acceptLanguage = trim((string)GeneralUtility::getIndpEnv('HTTP_ACCEPT_LANGUAGE'));
            if ($userAgent === '' && $acceptLanguage === '') {
                return 'bot';
            }
        }

        if ($this->isRateLimited()) {
            return 'rate';
        }

        return '';
    }

    private function isRateLimited(): bool
    {
        $rateLimit = (int)($this->settings['jobAlertRateLimit'] ?? 10);
        $windowSeconds = (int)($this->settings['jobAlertRateWindowSeconds'] ?? ($this->settings['botRateWindowSeconds'] ?? 3600));
        if ($rateLimit <= 0) {
            return false;
        }

        $ip = trim((string)GeneralUtility::getIndpEnv('REMOTE_ADDR'));
        if ($ip === '') {
            return false;
        }

        try {
            $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('aiscareer_rate');
        } catch (\Throwable) {
            return false;
        }

        $key = 'alert_' . md5($ip);
        $data = $cache->get($key);
        $count = is_array($data) ? (int)($data['count'] ?? 0) : 0;

        if ($count >= $rateLimit) {
            return true;
        }

        $cache->set($key, ['count' => $count + 1], [], $windowSeconds);

        return false;
    }

    private function sendOptInMail(string $email, array $filters, string $token, string $unsubscribeToken): void
    {
        $fromEmail = trim((string)($this->settings['jobAlertFromEmail'] ?? $this->settings['applicationFromEmail'] ?? ''));
        $fromName = trim((string)($this->settings['jobAlertFromName'] ?? 'AIS Career'));
        if ($fromEmail === '' || !GeneralUtility::validEmail($fromEmail)) {
            return;
        }

        $confirmUrl = $this->buildActionUrl('confirm', ['token' => $token]);
        $unsubscribeUrl = $this->buildActionUrl('unsubscribe', ['token' => $unsubscribeToken]);

        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename('EXT:ais_career/Resources/Private/Templates/Email/JobAlertOptIn.html');
        $view->assignMultiple([
            'confirmUrl' => $confirmUrl,
            'unsubscribeUrl' => $unsubscribeUrl,
            'filters' => $filters,
            'email' => $email,
        ]);

        $subject = LocalizationUtility::translate('mail.alert.optin.subject', 'AisCareer')
            ?? 'Please confirm your job alert subscription';

        $mail = GeneralUtility::makeInstance(MailMessage::class);
        $mail->setFrom([$fromEmail => $fromName !== '' ? $fromName : 'AIS Career']);
        $mail->setTo([$email]);
        $mail->setSubject($subject);
        $mail->html($view->render());
        $mail->send();
    }

    private function buildActionUrl(string $action, array $arguments): string
    {
        $uriBuilder = $this->uriBuilder->reset()->setCreateAbsoluteUri(true);
        $targetPid = $this->resolveAlertPagePid();
        if ($targetPid > 0) {
            $uriBuilder->setTargetPageUid($targetPid);
        }

        return $uriBuilder->uriFor($action, $arguments, 'Alert', 'AisCareer', 'JobAlert');
    }

    private function resolveAlertPagePid(): int
    {
        $pagePid = (int)($this->settings['jobAlertPagePid'] ?? 0);
        if ($pagePid > 0) {
            return $pagePid;
        }

        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($request instanceof ServerRequestInterface) {
            $routing = $request->getAttribute('routing');
            if ($routing instanceof PageArguments) {
                return (int)$routing->getPageId();
            }
            $query = $request->getQueryParams();
            if (isset($query['id']) && is_numeric($query['id'])) {
                return (int)$query['id'];
            }
        }

        return 0;
    }

    private function resolveStoragePid(): int
    {
        $storagePid = (int)($this->settings['jobAlertStoragePid'] ?? 0);
        if ($storagePid > 0) {
            return $storagePid;
        }

        $listPid = (int)($this->settings['listPid'] ?? 0);
        if ($listPid > 0) {
            return $listPid;
        }

        return $this->resolveAlertPagePid();
    }

    private function resolveAlertPageUrl(): string
    {
        return $this->buildActionUrl('form', []);
    }

    private function redirectToForm(string $state): ResponseInterface
    {
        return $this->redirectToUri($this->buildActionUrl('form', ['alertState' => $state]));
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function extractAlertData(): array
    {
        if ($this->request->hasArgument('jobAlert')) {
            $data = (array)$this->request->getArgument('jobAlert');
            if ($data !== []) {
                return $data;
            }
        }

        $httpRequest = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($httpRequest instanceof ServerRequestInterface) {
            $body = (array)$httpRequest->getParsedBody();
            $query = (array)$httpRequest->getQueryParams();
            $raw = $body['tx_aiscareer_jobalert']['jobAlert']
                ?? $body['tx_aiscareer_joblist']['jobAlert']
                ?? $body['jobAlert']
                ?? $query['tx_aiscareer_jobalert']['jobAlert']
                ?? $query['tx_aiscareer_joblist']['jobAlert']
                ?? $query['jobAlert']
                ?? null;
            if (is_array($raw)) {
                return $raw;
            }
        }

        return [];
    }

    private function extractAntiBotData(): array
    {
        if ($this->request->hasArgument('antiBot')) {
            $data = (array)$this->request->getArgument('antiBot');
            if ($data !== []) {
                return $data;
            }
        }

        $httpRequest = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($httpRequest instanceof ServerRequestInterface) {
            $body = (array)$httpRequest->getParsedBody();
            $query = (array)$httpRequest->getQueryParams();
            $raw = $body['tx_aiscareer_jobalert']['antiBot']
                ?? $body['tx_aiscareer_joblist']['antiBot']
                ?? $body['antiBot']
                ?? $query['tx_aiscareer_jobalert']['antiBot']
                ?? $query['tx_aiscareer_joblist']['antiBot']
                ?? $query['antiBot']
                ?? null;
            if (is_array($raw)) {
                return $raw;
            }
        }

        return [];
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value > 0;
        }
        if (is_string($value)) {
            $v = strtolower(trim($value));
            return in_array($v, ['1', 'true', 'on', 'yes'], true);
        }
        return false;
    }
}
