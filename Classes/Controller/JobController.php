<?php

declare(strict_types=1);

namespace Aistea\AisCareer\Controller;

use Aistea\AisCareer\Domain\Model\Application;
use Aistea\AisCareer\Domain\Model\Job;
use Aistea\AisCareer\Domain\Repository\ApplicationRepository;
use Aistea\AisCareer\Domain\Repository\JobRepository;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Core\Pagination\SimplePagination;

class JobController extends ActionController
{
    public function __construct(
        protected readonly JobRepository $jobRepository,
        protected readonly ApplicationRepository $applicationRepository,
        protected readonly PersistenceManagerInterface $persistenceManager
    ) {
    }

    public function listAction(): ResponseInterface
    {
        $this->addAssets();
        $settings = $this->settings;
        $itemsPerPage = max(1, (int)($settings['itemsPerPage'] ?? 10));
        $currentPage = 1;
        if ($this->request->hasArgument('page')) {
            $currentPage = max(1, (int)$this->request->getArgument('page'));
        }

        $filters = [];
        if (!empty($settings['enableFilters'])) {
            if ($this->request->hasArgument('filter')) {
                $filters = (array)$this->request->getArgument('filter');
            } else {
                $httpRequest = $GLOBALS['TYPO3_REQUEST'] ?? null;
                if ($httpRequest !== null) {
                    $body = (array)$httpRequest->getParsedBody();
                    $query = (array)$httpRequest->getQueryParams();
                    $raw = $body['tx_aiscareer_joblist']['filter'] ?? $query['tx_aiscareer_joblist']['filter'] ?? null;
                    if (is_array($raw)) {
                        $filters = $raw;
                    }
                }
            }
        }

        $jobs = $this->jobRepository->findActiveFiltered($filters);
        $paginator = new QueryResultPaginator($jobs, $currentPage, $itemsPerPage);
        $pagination = new SimplePagination($paginator);

        $filterOptions = $this->collectFilterOptions();
        $availableCountries = array_values(array_unique(array_map('strtoupper', array_keys($filterOptions['countries']))));
        $detailPid = (int)($settings['detailPid'] ?? 0);
        $listPid = (int)($settings['listPid'] ?? 0);

        $this->view->assign('jobs', $paginator->getPaginatedItems());
        $this->view->assign('paginator', $paginator);
        $this->view->assign('pagination', $pagination);
        $this->view->assign('filters', $filters);
        $this->view->assign('filterOptions', $filterOptions);
        $this->view->assign('availableCountries', $availableCountries);
        $this->view->assign('detailPid', $detailPid);
        $this->view->assign('listPid', $listPid);
        $this->view->assign('settings', $settings);

        return $this->htmlResponse();
    }

    public function showAction(?Job $job = null, ?Application $application = null, array $applicationErrors = []): ResponseInterface
    {
        $this->addAssets();
        if ($job === null) {
            throw new PageNotFoundException('Job not specified');
        }
        if (!$this->jobRepository->isJobVisible($job)) {
            throw new PageNotFoundException('Job not available');
        }

        if ($application === null) {
            $application = new Application();
        }
        $applicationSuccess = false;
        if ($this->request->hasArgument('applicationSuccess')) {
            $applicationSuccess = (bool)$this->request->getArgument('applicationSuccess');
        }

        return $this->renderShow($job, $application, $applicationErrors, $applicationSuccess);
    }

    public function initializeApplyAction(): void
    {
        if (!$this->arguments->hasArgument('application')) {
            return;
        }

        $allowedExtensions = (string)($this->settings['allowedExtensions'] ?? 'pdf,doc,docx');
        $maxUploadSizeMb = (int)($this->settings['maxUploadSizeMB'] ?? 5);
        $maxUploadSizeBytes = $maxUploadSizeMb * 1024 * 1024;

        $configuration = $this->arguments->getArgument('application')->getPropertyMappingConfiguration();
        $converterClass = 'TYPO3\\CMS\\Extbase\\Property\\TypeConverter\\UploadedFileReferenceConverter';
        if (class_exists($converterClass)) {
            $configuration->forProperty('cvFile')->setTypeConverterOptions(
                $converterClass,
                [
                    $converterClass::CONFIGURATION_ALLOWED_FILE_EXTENSIONS => $allowedExtensions,
                    $converterClass::CONFIGURATION_MAX_UPLOAD_SIZE => $maxUploadSizeBytes,
                    $converterClass::CONFIGURATION_UPLOAD_FOLDER => '1:/user_upload/ais_career/',
                ]
            );
        }
    }

    public function applyAction(Job $job, Application $application): ResponseInterface
    {
        if (empty($this->settings['applicationEnabled'])) {
            throw new PageNotFoundException('Applications are disabled');
        }

        if (!$this->jobRepository->isJobVisible($job)) {
            throw new PageNotFoundException('Job not available');
        }

        $application->setJob($job);

        $errors = $this->validateApplication($application);
        $botErrors = $this->validateBotSignals($job);
        if ($botErrors !== []) {
            $errors = array_merge($errors, $botErrors);
        }
        if ($errors !== []) {
            return $this->renderShow($job, $application, $errors, false);
        }

        $application->setCreatedAt(new \DateTime());

        if (!empty($this->settings['applicationSave'])) {
            $this->applicationRepository->add($application);
            $this->persistenceManager->persistAll();
        }

        $this->sendApplicationMail($job, $application);

        return $this->redirect('show', null, null, ['job' => $job, 'applicationSuccess' => 1]);
    }

    private function collectFilterOptions(): array
    {
        $options = [
            'countries' => [],
            'departments' => [],
            'contractTypes' => [],
            'categories' => [],
        ];

        foreach ($this->jobRepository->findActive() as $job) {
            $country = strtoupper(trim($job->getCountry()));
            if ($country !== '') {
                $options['countries'][$country] = $country;
            }
            $department = trim($job->getDepartment());
            if ($department !== '') {
                $options['departments'][$department] = $department;
            }
            $contractType = trim($job->getContractType());
            if ($contractType !== '') {
                $options['contractTypes'][$contractType] = $contractType;
            }
            foreach ($job->getCategories() as $category) {
                $options['categories'][$category->getUid()] = $category->getTitle();
            }
        }

        ksort($options['countries']);
        ksort($options['departments']);
        ksort($options['contractTypes']);
        asort($options['categories']);

        return $options;
    }

    private function validateApplication(Application $application): array
    {
        $errors = [];
        $t = static fn (string $key, string $fallback): string => LocalizationUtility::translate($key, 'AisCareer') ?? $fallback;

        if (trim($application->getFirstName()) === '') {
            $errors['firstName'] = $t('error.firstNameRequired', 'First name is required.');
        }
        if (trim($application->getLastName()) === '') {
            $errors['lastName'] = $t('error.lastNameRequired', 'Last name is required.');
        }
        if (trim($application->getEmail()) === '') {
            $errors['email'] = $t('error.emailRequired', 'Email is required.');
        } elseif (!GeneralUtility::validEmail($application->getEmail())) {
            $errors['email'] = $t('error.emailInvalid', 'Email is invalid.');
        }
        if (!$application->isConsentPrivacy()) {
            $errors['consentPrivacy'] = $t('error.consentRequired', 'Privacy consent is required.');
        }

        $fileReference = $application->getCvFile();
        if ($fileReference !== null) {
            $resource = $fileReference->getOriginalResource();
            if ($resource !== null) {
                $file = $resource->getOriginalFile();
                $extension = strtolower((string)$file->getExtension());
                $allowed = array_filter(array_map('trim', explode(',', (string)($this->settings['allowedExtensions'] ?? 'pdf,doc,docx'))));
                if ($allowed !== [] && !in_array($extension, $allowed, true)) {
                    $errors['cvFile'] = $t('error.fileType', 'File type is not allowed.');
                }
                $maxUploadSizeMb = (int)($this->settings['maxUploadSizeMB'] ?? 5);
                $maxUploadSizeBytes = $maxUploadSizeMb * 1024 * 1024;
                if ($file->getSize() > $maxUploadSizeBytes) {
                    $errors['cvFile'] = $t('error.fileTooLarge', 'File is too large.');
                }
            }
        }

        return $errors;
    }

    private function validateBotSignals(Job $job): array
    {
        $errors = [];
        $t = static fn (string $key, string $fallback): string => LocalizationUtility::translate($key, 'AisCareer') ?? $fallback;

        $antiBot = [];
        if ($this->request->hasArgument('antiBot')) {
            $antiBot = (array)$this->request->getArgument('antiBot');
        } else {
            $httpRequest = $GLOBALS['TYPO3_REQUEST'] ?? null;
            if ($httpRequest !== null) {
                $body = (array)$httpRequest->getParsedBody();
                $raw = $body['tx_aiscareer_jobdetail']['antiBot'] ?? $body['antiBot'] ?? null;
                if (is_array($raw)) {
                    $antiBot = $raw;
                }
            }
        }

        $honeypot = trim((string)($antiBot['website'] ?? ''));
        if ($honeypot !== '') {
            $errors['_bot'] = $t('error.botDetected', 'Please try again.');
            return $errors;
        }

        $minSeconds = (int)($this->settings['botMinSeconds'] ?? 3);
        $maxSeconds = (int)($this->settings['botMaxSeconds'] ?? 86400);
        $ts = (int)($antiBot['ts'] ?? 0);
        $now = (int)(new \DateTime())->format('U');
        if ($ts > 0) {
            $age = $now - $ts;
            if (($minSeconds > 0 && $age < $minSeconds) || ($maxSeconds > 0 && $age > $maxSeconds)) {
                $errors['_bot'] = $t('error.botDetected', 'Please try again.');
                return $errors;
            }
        }

        if (!empty($this->settings['botRequireHeaders'])) {
            $userAgent = trim((string)GeneralUtility::getIndpEnv('HTTP_USER_AGENT'));
            $acceptLanguage = trim((string)GeneralUtility::getIndpEnv('HTTP_ACCEPT_LANGUAGE'));
            if ($userAgent === '' && $acceptLanguage === '') {
                $errors['_bot'] = $t('error.botDetected', 'Please try again.');
                return $errors;
            }
        }

        if ($this->isRateLimited($job)) {
            $errors['_bot'] = $t('error.rateLimited', 'Too many submissions. Please try again later.');
            return $errors;
        }

        return $errors;
    }

    private function isRateLimited(Job $job): bool
    {
        $rateLimit = (int)($this->settings['botRateLimit'] ?? 5);
        $windowSeconds = (int)($this->settings['botRateWindowSeconds'] ?? 3600);
        if ($rateLimit <= 0) {
            return false;
        }

        $ip = trim((string)GeneralUtility::getIndpEnv('REMOTE_ADDR'));
        if ($ip === '') {
            return false;
        }

        try {
            $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('aiscareer_rate');
        } catch (\TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException) {
            return false;
        }

        $key = 'ip_' . md5($ip . '|' . $job->getUid());
        $data = $cache->get($key);
        $count = 0;
        if (is_array($data) && isset($data['count'])) {
            $count = (int)$data['count'];
        }

        if ($count >= $rateLimit) {
            return true;
        }

        $cache->set($key, ['count' => $count + 1], [], $windowSeconds);
        return false;
    }

    private function sendApplicationMail(Job $job, Application $application): void
    {
        $toEmail = trim($job->getContactEmail());
        if ($toEmail === '') {
            $toEmail = (string)($this->settings['applicationToEmail'] ?? '');
        }
        $fromEmail = (string)($this->settings['applicationFromEmail'] ?? '');
        if ($toEmail === '' || $fromEmail === '') {
            return;
        }

        $safe = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $subject = 'Application for ' . $job->getTitle();
        if ($job->getReference() !== '') {
            $subject .= ' (' . $job->getReference() . ')';
        }

        $bodyLines = [
            'New application received',
            '',
            'Job: ' . $job->getTitle(),
            'Reference: ' . $job->getReference(),
            'Name: ' . $application->getFirstName() . ' ' . $application->getLastName(),
            'Email: ' . $application->getEmail(),
            'Phone: ' . $application->getPhone(),
            '',
            'Message:',
            $application->getMessage(),
        ];

        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename('EXT:ais_career/Resources/Private/Templates/Email/AdminNotification.html');
        $view->assignMultiple([
            'job' => $job,
            'application' => $application,
        ]);
        $htmlBody = $view->render();

        $mail = GeneralUtility::makeInstance(MailMessage::class);
        $mail->setFrom([$fromEmail => 'AIS Career']);
        $mail->setTo([$toEmail]);
        if (GeneralUtility::validEmail($application->getEmail())) {
            $mail->setReplyTo([$application->getEmail() => $application->getFirstName() . ' ' . $application->getLastName()]);
        }
        $mail->setSubject($subject);
        $mail->text(implode("\n", $bodyLines));
        $mail->html($htmlBody);

        $fileReference = $application->getCvFile();
        if ($fileReference !== null) {
            $resource = $fileReference->getOriginalResource();
            if ($resource !== null) {
                $file = $resource->getOriginalFile();
                $localFile = $file->getForLocalProcessing(false);
                if (is_string($localFile) && $localFile !== '' && file_exists($localFile)) {
                    $mail->attachFromPath($localFile, $file->getName());
                }
            }
        }

        $mail->send();

        if (!empty($this->settings['applicationConfirmationEnabled'])) {
            $this->sendApplicantConfirmationMail($job, $application, $fromEmail, $toEmail);
        }
    }

    private function sendApplicantConfirmationMail(Job $job, Application $application, string $fromEmail, string $replyToEmail): void
    {
        $applicantEmail = trim($application->getEmail());
        if ($applicantEmail === '' || !GeneralUtility::validEmail($applicantEmail)) {
            return;
        }
        if ($fromEmail === '') {
            return;
        }

        $t = static fn (string $key, string $fallback, array $args = []): string => LocalizationUtility::translate($key, 'AisCareer', $args) ?? $fallback;

        $subject = $t('mail.confirm.subject', 'We received your application — %s', [$job->getTitle()]);
        if ($job->getReference() !== '') {
            $subject .= ' (' . $job->getReference() . ')';
        }

        $textBody = $t('mail.confirm.title', 'Application received') . "\n\n"
            . $t('mail.confirm.greeting', 'Hi %s %s,', [$application->getFirstName(), $application->getLastName()]) . "\n\n"
            . $t('mail.confirm.body', 'Thank you for your application. We’ve received your submission and our team will review it shortly.') . "\n\n"
            . $t('mail.confirm.positionLabel', 'Position') . ': ' . $job->getTitle() . "\n"
            . ($job->getReference() !== '' ? $t('mail.confirm.referenceLabel', 'Reference:') . ' ' . $job->getReference() . "\n" : '')
            . "\n" . $t('mail.confirm.footer', 'If you have any questions, just reply to this email.') . "\n";

        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename('EXT:ais_career/Resources/Private/Templates/Email/ApplicantConfirmation.html');
        $view->assignMultiple([
            'job' => $job,
            'application' => $application,
        ]);
        $htmlBody = $view->render();

        $mail = GeneralUtility::makeInstance(MailMessage::class);
        $mail->setFrom([$fromEmail => 'AIS Career']);
        $mail->setTo([$applicantEmail => $application->getFirstName() . ' ' . $application->getLastName()]);
        if (GeneralUtility::validEmail($replyToEmail)) {
            $mail->setReplyTo([$replyToEmail]);
        }
        $mail->setSubject($subject);
        $mail->text($textBody);
        $mail->html($htmlBody);
        $mail->send();
    }

    private function renderShow(Job $job, Application $application, array $applicationErrors, bool $applicationSuccess): ResponseInterface
    {
        $listPid = (int)($this->settings['listPid'] ?? 0);
        $jobPostingJsonLd = $this->buildJobPostingJsonLd($job);
        $this->view->assignMultiple([
            'job' => $job,
            'application' => $application,
            'applicationErrors' => $applicationErrors,
            'applicationSuccess' => $applicationSuccess,
            'formTimestamp' => (new \DateTime())->getTimestamp(),
            'jobPostingJsonLd' => $jobPostingJsonLd,
            'listPid' => $listPid,
            'settings' => $this->settings,
        ]);

        $templatePath = GeneralUtility::getFileAbsFileName('EXT:ais_career/Resources/Private/Templates/Job/Show.html');
        if (method_exists($this->view, 'setTemplatePathAndFilename')) {
            $this->view->setTemplatePathAndFilename($templatePath);
        }

        return $this->htmlResponse();
    }

    private function buildJobPostingJsonLd(Job $job): string
    {
        $description = trim(strip_tags((string)$job->getDescription()));
        if ($description === '') {
            $description = trim(strip_tags((string)$job->getResponsibilities()));
        }
        if ($description === '') {
            $description = trim(strip_tags((string)$job->getQualifications()));
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => $job->getTitle(),
        ];

        if ($description !== '') {
            $data['description'] = $description;
        }
        if ($job->getReference() !== '') {
            $data['identifier'] = [
                '@type' => 'PropertyValue',
                'name' => 'Reference',
                'value' => $job->getReference(),
            ];
        }
        if ($job->getContractType() !== '') {
            $data['employmentType'] = $job->getContractType();
        }
        if ($job->getPublishedFrom() instanceof \DateTime) {
            $data['datePosted'] = $job->getPublishedFrom()->format('Y-m-d');
        }
        if ($job->getPublishedTo() instanceof \DateTime) {
            $data['validThrough'] = $job->getPublishedTo()->format('Y-m-d');
        }

        $city = trim((string)$job->getCity());
        $country = trim((string)$job->getCountry());
        if ($city !== '' || $country !== '') {
            $data['jobLocation'] = [
                '@type' => 'Place',
                'address' => [
                    '@type' => 'PostalAddress',
                ],
            ];
            if ($city !== '') {
                $data['jobLocation']['address']['addressLocality'] = $city;
            }
            if ($country !== '') {
                $data['jobLocation']['address']['addressCountry'] = $country;
            }
        }
        if ($job->isRemotePossible()) {
            $data['jobLocationType'] = 'TELECOMMUTE';
        }

        $orgName = trim((string)($this->settings['hiringOrganizationName'] ?? ''));
        if ($orgName === '' && $this->configurationManager instanceof ConfigurationManagerInterface) {
            $settings = $this->configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
                'AisCareer',
                'JobDetail'
            );
            $orgName = trim((string)($settings['hiringOrganizationName'] ?? ''));
        }
        if ($orgName === '') {
            $site = ($GLOBALS['TYPO3_REQUEST'] ?? null)?->getAttribute('site');
            if ($site instanceof \TYPO3\CMS\Core\Site\Entity\Site) {
                $siteConfig = $site->getConfiguration();
                $orgName = trim((string)($siteConfig['websiteTitle'] ?? $siteConfig['siteName'] ?? ''));
            }
        }
        if ($orgName !== '') {
            $data['hiringOrganization'] = [
                '@type' => 'Organization',
                'name' => $orgName,
            ];
        }

        return (string)json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function addAssets(): void
    {
        $assetCollector = GeneralUtility::makeInstance(AssetCollector::class);
        $assetCollector->addStyleSheet('aiscareer', 'EXT:ais_career/Resources/Public/Css/aiscareer.css');
        $assetCollector->addJavaScript('aiscareer', 'EXT:ais_career/Resources/Public/JavaScript/aiscareer.js');

        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->addCssFile('EXT:ais_career/Resources/Public/Css/aiscareer.css');
        $pageRenderer->addJsFooterFile('EXT:ais_career/Resources/Public/JavaScript/aiscareer.js');
    }
}
