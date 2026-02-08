<?php

declare(strict_types=1);

namespace Aistea\AisCareer\Controller;

use Aistea\AisCareer\Domain\Model\Application;
use Aistea\AisCareer\Domain\Model\Job;
use Aistea\AisCareer\Domain\Repository\ApplicationRepository;
use Aistea\AisCareer\Domain\Repository\EventRepository;
use Aistea\AisCareer\Domain\Repository\JobRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
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
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\FileReference as CoreFileReference;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Core\Pagination\SimplePagination;

class JobController extends ActionController
{
    public function __construct(
        protected readonly JobRepository $jobRepository,
        protected readonly ApplicationRepository $applicationRepository,
        protected readonly EventRepository $eventRepository,
        protected readonly PersistenceManagerInterface $persistenceManager
    ) {
    }

    public function listAction(): ResponseInterface
    {
        $this->setLocaleFromRequest();
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

        $this->trackListView();

        return $this->htmlResponse();
    }

    public function showAction(?Job $job = null, ?Application $application = null, array $applicationErrors = []): ResponseInterface
    {
        $this->setLocaleFromRequest();
        $this->addAssets();
        if ($job === null) {
            $job = $this->resolveJobFromRequest();
        }
        if ($job === null) {
            throw new PageNotFoundException('Job not specified');
        }
        if (!$this->jobRepository->isJobVisible($job)) {
            throw new PageNotFoundException('Job not available');
        }

        $this->trackDetailView($job);

        if ($application === null) {
            $application = new Application();
        }
        $applicationSuccess = false;
        if ($this->request->hasArgument('applicationSuccess')) {
            $applicationSuccess = (bool)$this->request->getArgument('applicationSuccess');
        }

        return $this->renderShow($job, $application, $applicationErrors, $applicationSuccess, '');
    }

    private function resolveJobFromRequest(): ?Job
    {
        if ($this->request->hasArgument('job')) {
            $raw = $this->request->getArgument('job');
            if (is_numeric($raw)) {
                $job = $this->jobRepository->findByUid((int)$raw);
                if ($job instanceof Job) {
                    return $job;
                }
            }
            if (is_string($raw) && $raw !== '') {
                $job = $this->jobRepository->findOneBySlug($raw);
                if ($job instanceof Job) {
                    return $job;
                }
            }
        }

        $httpRequest = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($httpRequest instanceof ServerRequestInterface) {
            $query = $httpRequest->getQueryParams();
            $slug = $query['job-slug'] ?? $query['job'] ?? null;
            if (is_string($slug) && $slug !== '') {
                $job = $this->jobRepository->findOneBySlug($slug);
                if ($job instanceof Job) {
                    return $job;
                }
            }
        }

        return null;
    }

    public function initializeApplyAction(): void
    {
        if (!$this->arguments->hasArgument('application')) {
            return;
        }

        $allowedExtensions = (string)($this->settings['allowedExtensions'] ?? 'pdf,png,jpg,jpeg');
        $maxUploadSizeMb = (int)($this->settings['maxUploadSizeMB'] ?? 5);
        $maxUploadSizeBytes = $maxUploadSizeMb * 1024 * 1024;

        $configuration = $this->arguments->getArgument('application')->getPropertyMappingConfiguration();
        $converterClass = 'TYPO3\\CMS\\Extbase\\Property\\TypeConverter\\UploadedFileReferenceConverter';
        if (class_exists($converterClass)) {
            $fileOptions = [
                $converterClass::CONFIGURATION_ALLOWED_FILE_EXTENSIONS => $allowedExtensions,
                $converterClass::CONFIGURATION_MAX_UPLOAD_SIZE => $maxUploadSizeBytes,
                $converterClass::CONFIGURATION_UPLOAD_FOLDER => '1:/user_upload/ais_career/',
            ];

            $configuration->forProperty('cvFile')->setTypeConverterOptions($converterClass, $fileOptions);
            $configuration->forProperty('portfolioFile')->setTypeConverterOptions($converterClass, $fileOptions);
            $configuration->forProperty('additionalFile')->setTypeConverterOptions($converterClass, $fileOptions);
        }
    }

    public function applyAction(Job $job): ResponseInterface
    {
        $this->setLocaleFromRequest();
        if (empty($this->settings['applicationEnabled'])) {
            throw new PageNotFoundException('Applications are disabled');
        }

        if (!$this->jobRepository->isJobVisible($job)) {
            throw new PageNotFoundException('Job not available');
        }

        $applicationData = $this->request->hasArgument('application')
            ? (array)$this->request->getArgument('application')
            : [];

        $application = $this->buildApplicationFromRequest($applicationData);
        $application->setJob($job);

        $errors = $this->validateApplication($application);
        $fileErrors = $this->validateUploadsFromRequest();
        $botErrors = $this->validateBotSignals($job);
        if ($fileErrors !== []) {
            $errors = array_merge($errors, $fileErrors);
        }
        if ($botErrors !== []) {
            $errors = array_merge($errors, $botErrors);
        }
        if ($errors !== []) {
            return $this->renderShow($job, $application, $errors, false, '');
        }

        $application->setCreatedAt(new \DateTime());
        $this->trackApplicationSubmit($job);

        $doubleOptInEnabled = !empty($this->settings['applicationDoubleOptInEnabled']);
        $hasUploads = $this->hasUploadedFiles();

        if (!empty($this->settings['applicationSave']) || $doubleOptInEnabled || $hasUploads) {
            $this->applicationRepository->add($application);
            $this->persistenceManager->persistAll();
        }

        if ($hasUploads) {
            $this->attachUploadedFilesToApplication($application);
            $this->applicationRepository->update($application);
            $this->persistenceManager->persistAll();
        }

        if ($doubleOptInEnabled) {
            $application->setDoubleOptInToken($this->generateOptInToken());
            $application->setDoubleOptInConfirmedAt(null);
            $this->applicationRepository->update($application);
            $this->persistenceManager->persistAll();
            $this->sendDoubleOptInMail($job, $application);

            return $this->renderShow($job, $application, [], false, 'pending');
        }

        $this->sendApplicationMail($job, $application);

        return $this->redirect('show', null, null, ['job' => $job, 'applicationSuccess' => 1]);
    }

    public function confirmAction(?Job $job = null, string $token = ''): ResponseInterface
    {
        $this->setLocaleFromRequest();
        if ($token === '') {
            throw new PageNotFoundException('Confirmation token missing');
        }

        $application = $this->applicationRepository->findOneByDoubleOptInToken($token);
        if ($application instanceof Application) {
            if ($job === null) {
                $job = $application->getJob();
            }
        }

        if (!$job instanceof Job) {
            throw new PageNotFoundException('Job not specified');
        }

        if (!$application instanceof Application || $application->getJob()?->getUid() !== $job->getUid()) {
            return $this->renderShow($job, new Application(), [], false, 'invalid');
        }

        if ($application->getDoubleOptInConfirmedAt() instanceof \DateTime) {
            return $this->renderShow($job, $application, [], false, 'confirmed');
        }

        $application->setDoubleOptInConfirmedAt(new \DateTime());
        $application->setDoubleOptInToken('');
        $this->applicationRepository->update($application);
        $this->persistenceManager->persistAll();

        $this->sendApplicationMail($job, $application);

        return $this->renderShow($job, $application, [], false, 'confirmed');
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
        $locale = $this->getLocaleFromRequest();
        $label = \Locale::getDisplayRegion('-' . $countryCode, $locale);
        return $label !== '' ? $label : $countryCode;
    }

    private function getLocaleFromRequest(): string
    {
        $httpRequest = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($httpRequest instanceof ServerRequestInterface) {
            $language = $httpRequest->getAttribute('language');
            if ($language instanceof \TYPO3\CMS\Core\Site\Entity\SiteLanguage) {
                $locale = (string)$language->getLocale();
                if ($locale !== '') {
                    return $locale;
                }
            }
        }
        return 'en';
    }

    private function setLocaleFromRequest(): void
    {
        if (!class_exists(\Locale::class)) {
            return;
        }
        $locale = $this->getLocaleFromRequest();
        if ($locale !== '') {
            \Locale::setDefault($locale);
        }
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

        $allowed = array_filter(array_map('trim', explode(',', (string)($this->settings['allowedExtensions'] ?? 'pdf,png,jpg,jpeg'))));
        $maxUploadSizeMb = (int)($this->settings['maxUploadSizeMB'] ?? 5);
        $maxUploadSizeBytes = $maxUploadSizeMb * 1024 * 1024;

        $this->validateUpload($application->getCvFile(), 'cvFile', $allowed, $maxUploadSizeBytes, $errors, $t);
        $this->validateUpload($application->getPortfolioFile(), 'portfolioFile', $allowed, $maxUploadSizeBytes, $errors, $t);
        $this->validateUpload($application->getAdditionalFile(), 'additionalFile', $allowed, $maxUploadSizeBytes, $errors, $t);

        return $errors;
    }

    private function buildApplicationFromRequest(array $applicationData): Application
    {
        $application = new Application();
        $application->setFirstName(trim((string)($applicationData['firstName'] ?? '')));
        $application->setLastName(trim((string)($applicationData['lastName'] ?? '')));
        $application->setEmail(trim((string)($applicationData['email'] ?? '')));
        $application->setPhone(trim((string)($applicationData['phone'] ?? '')));
        $application->setMessage(trim((string)($applicationData['message'] ?? '')));
        $application->setConsentPrivacy((bool)($applicationData['consentPrivacy'] ?? false));

        return $application;
    }

    private function validateUploadsFromRequest(): array
    {
        $errors = [];
        $t = static fn (string $key, string $fallback): string => LocalizationUtility::translate($key, 'AisCareer') ?? $fallback;
        $allowed = array_filter(array_map('trim', explode(',', (string)($this->settings['allowedExtensions'] ?? 'pdf,png,jpg,jpeg'))));
        $maxUploadSizeMb = (int)($this->settings['maxUploadSizeMB'] ?? 5);
        $maxUploadSizeBytes = $maxUploadSizeMb * 1024 * 1024;
        $maxTotalUploadSizeMb = (int)($this->settings['maxTotalUploadSizeMB'] ?? 0);
        $maxTotalUploadSizeBytes = $maxTotalUploadSizeMb > 0 ? $maxTotalUploadSizeMb * 1024 * 1024 : 0;
        $allowedMimeTypes = [
            'pdf' => ['application/pdf'],
            'png' => ['image/png'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
        ];
        $totalSize = 0;

        foreach (['cvFile', 'portfolioFile', 'additionalFile'] as $field) {
            $file = $this->getUploadedFileFromRequest($field);
            if (!$file instanceof UploadedFileInterface || $file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($file->getError() !== UPLOAD_ERR_OK) {
                $errors[$field] = $t('error.fileType', 'File type is not allowed.');
                continue;
            }
            $name = strtolower((string)$file->getClientFilename());
            $extension = $name !== '' ? strtolower((string)pathinfo($name, PATHINFO_EXTENSION)) : '';
            if ($allowed !== [] && $extension !== '' && !in_array($extension, $allowed, true)) {
                $errors[$field] = $t('error.fileType', 'File type is not allowed.');
                continue;
            }
            if ($extension !== '' && isset($allowedMimeTypes[$extension])) {
                $tmpPath = $this->getUploadedTempPath($file);
                $mime = $this->detectMimeType($tmpPath);
                if ($mime === '' || !in_array($mime, $allowedMimeTypes[$extension], true)) {
                    $errors[$field] = $t('error.fileType', 'File type is not allowed.');
                    continue;
                }
            }
            $size = $file->getSize() ?? 0;
            $totalSize += $size;
            if ($size > $maxUploadSizeBytes) {
                $errors[$field] = $t('error.fileTooLarge', 'File is too large.');
            }
        }

        if ($maxTotalUploadSizeBytes > 0 && $totalSize > $maxTotalUploadSizeBytes) {
            $errors['_total'] = $t('error.fileTotalTooLarge', 'Total upload size is too large.');
        }

        return $errors;
    }

    private function hasUploadedFiles(): bool
    {
        foreach (['cvFile', 'portfolioFile', 'additionalFile'] as $field) {
            $file = $this->getUploadedFileFromRequest($field);
            if ($file instanceof UploadedFileInterface && $file->getError() !== UPLOAD_ERR_NO_FILE) {
                return true;
            }
        }
        return false;
    }

    private function attachUploadedFilesToApplication(Application $application): void
    {
        $folder = $this->getOrCreateApplicationFolder($application);
        if (!$folder instanceof Folder) {
            return;
        }

        $application->setCvFile($this->convertUploadedFile('cvFile', $folder));
        $application->setPortfolioFile($this->convertUploadedFile('portfolioFile', $folder));
        $application->setAdditionalFile($this->convertUploadedFile('additionalFile', $folder));
    }

    private function convertUploadedFile(string $field, Folder $folder): ?FileReference
    {
        $file = $this->getUploadedFileFromRequest($field);
        if (!$file instanceof UploadedFileInterface) {
            return null;
        }
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return null;
        }
        $tmpPath = $this->getUploadedTempPath($file);
        if ($tmpPath === '' || !is_file($tmpPath)) {
            return null;
        }

        try {
            $clientName = trim((string)$file->getClientFilename());
            $safeName = $clientName !== '' ? $clientName : ('upload_' . uniqid('', true));

            $storage = $folder->getStorage();
            $falFile = $storage->addFile($tmpPath, $folder, $safeName, DuplicationBehavior::RENAME);
            $falFileReference = $storage->createFileReferenceObject($falFile);
        } catch (\Throwable) {
            return null;
        }

        $fileReference = GeneralUtility::makeInstance(FileReference::class);
        $fileReference->setOriginalResource($falFileReference);

        return $fileReference;
    }

    private function getOrCreateApplicationFolder(Application $application): ?Folder
    {
        $uid = (int)$application->getUid();
        if ($uid <= 0) {
            return null;
        }

        $storage = GeneralUtility::makeInstance(ResourceFactory::class)->getStorageObject(1);
        $baseIdentifier = 'user_upload/ais_career/';
        $baseFolder = $storage->hasFolder($baseIdentifier)
            ? $storage->getFolder($baseIdentifier)
            : $storage->createFolder($baseIdentifier);

        $nameParts = trim($application->getFirstName() . ' ' . $application->getLastName());
        $slug = $this->slugifyFolderName($nameParts);
        $folderName = $uid . ($slug !== '' ? '-' . $slug : '');
        if ($baseFolder->hasFolder($folderName)) {
            return $baseFolder->getSubfolder($folderName);
        }

        return $storage->createFolder($folderName, $baseFolder);
    }

    private function slugifyFolderName(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9]+/', '-', $name) ?? '';
        $name = trim($name, '-');
        if (strlen($name) > 60) {
            $name = substr($name, 0, 60);
            $name = rtrim($name, '-');
        }
        return $name;
    }

    private function getUploadedFileFromRequest(string $field): ?UploadedFileInterface
    {
        $psrRequest = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$psrRequest instanceof ServerRequestInterface) {
            return null;
        }

        $uploadedFiles = $psrRequest->getUploadedFiles();
        $namespace = $this->buildPluginNamespaceKey();

        $file = $uploadedFiles[$namespace]['application'][$field]
            ?? $uploadedFiles['application'][$field]
            ?? null;

        return $file instanceof UploadedFileInterface ? $file : null;
    }

    private function buildPluginNamespaceKey(): string
    {
        $extension = strtolower((string)$this->request->getControllerExtensionName());
        $plugin = strtolower((string)$this->request->getPluginName());
        return 'tx_' . $extension . '_' . $plugin;
    }

    private function uploadedFileToLegacyArray(UploadedFileInterface $file): array
    {
        $tmpName = '';
        if (method_exists($file, 'getTemporaryFileName')) {
            $tmpName = (string)$file->getTemporaryFileName();
        }
        if ($tmpName === '') {
            $meta = $file->getStream()->getMetadata();
            if (is_array($meta) && isset($meta['uri'])) {
                $tmpName = (string)$meta['uri'];
            }
        }

        return [
            'name' => (string)$file->getClientFilename(),
            'type' => (string)$file->getClientMediaType(),
            'tmp_name' => $tmpName,
            'error' => $file->getError(),
            'size' => $file->getSize() ?? 0,
        ];
    }

    private function getUploadedTempPath(UploadedFileInterface $file): string
    {
        if (method_exists($file, 'getTemporaryFileName')) {
            $tmpName = (string)$file->getTemporaryFileName();
            if ($tmpName !== '') {
                return $tmpName;
            }
        }
        $meta = $file->getStream()->getMetadata();
        if (is_array($meta) && isset($meta['uri'])) {
            return (string)$meta['uri'];
        }

        return '';
    }

    private function detectMimeType(string $path): string
    {
        if ($path === '' || !is_file($path)) {
            return '';
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        return is_string($mime) ? $mime : '';
    }

    private function validateUpload(?\TYPO3\CMS\Extbase\Domain\Model\FileReference $fileReference, string $field, array $allowed, int $maxUploadSizeBytes, array &$errors, callable $t): void
    {
        if ($fileReference === null) {
            return;
        }
        $resource = $fileReference->getOriginalResource();
        if ($resource === null) {
            return;
        }
        $file = $resource->getOriginalFile();
        $extension = strtolower((string)$file->getExtension());
        if ($allowed !== [] && !in_array($extension, $allowed, true)) {
            $errors[$field] = $t('error.fileType', 'File type is not allowed.');
            return;
        }
        if ($file->getSize() > $maxUploadSizeBytes) {
            $errors[$field] = $t('error.fileTooLarge', 'File is too large.');
        }
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

        $this->attachFileToMail($mail, $application->getCvFile());
        $this->attachFileToMail($mail, $application->getPortfolioFile());
        $this->attachFileToMail($mail, $application->getAdditionalFile());

        $mail->send();

        if (!empty($this->settings['applicationConfirmationEnabled'])) {
            $this->sendApplicantConfirmationMail($job, $application, $fromEmail, $toEmail);
        }
    }

    private function sendDoubleOptInMail(Job $job, Application $application): void
    {
        $applicantEmail = trim($application->getEmail());
        $fromEmail = (string)($this->settings['applicationFromEmail'] ?? '');
        if ($applicantEmail === '' || !GeneralUtility::validEmail($applicantEmail) || $fromEmail === '') {
            return;
        }

        $subject = LocalizationUtility::translate(
            'mail.optin.subject',
            'AisCareer',
            [$job->getTitle()]
        ) ?? ('Please confirm your application — ' . $job->getTitle());
        if ($job->getReference() !== '') {
            $subject .= ' (' . $job->getReference() . ')';
        }

        $confirmUrl = $this->buildOptInConfirmUrl($job, $application);

        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename('EXT:ais_career/Resources/Private/Templates/Email/ApplicantOptIn.html');
        $view->assignMultiple([
            'job' => $job,
            'application' => $application,
            'confirmUrl' => $confirmUrl,
        ]);
        $htmlBody = $view->render();

        $mail = GeneralUtility::makeInstance(MailMessage::class);
        $mail->setFrom([$fromEmail => 'AIS Career']);
        $mail->setTo([$applicantEmail => $application->getFirstName() . ' ' . $application->getLastName()]);
        $mail->setSubject($subject);
        $mail->html($htmlBody);
        $mail->send();
    }

    private function buildOptInConfirmUrl(Job $job, Application $application): string
    {
        $pageUid = (int)($this->settings['detailPid'] ?? 0);
        $uriBuilder = $this->uriBuilder->reset()->setCreateAbsoluteUri(true);
        if ($pageUid > 0) {
            $uriBuilder->setTargetPageUid($pageUid);
        }

        return $uriBuilder->uriFor('confirm', ['job' => $job, 'token' => $application->getDoubleOptInToken()], 'Job');
    }

    private function generateOptInToken(): string
    {
        return bin2hex(random_bytes(32));
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

    private function attachFileToMail(MailMessage $mail, ?\TYPO3\CMS\Extbase\Domain\Model\FileReference $fileReference): void
    {
        if ($fileReference === null) {
            return;
        }
        $resource = $fileReference->getOriginalResource();
        if ($resource === null) {
            return;
        }
        $file = $resource->getOriginalFile();
        $localFile = $file->getForLocalProcessing(false);
        if (is_string($localFile) && $localFile !== '' && file_exists($localFile)) {
            $mail->attachFromPath($localFile, $file->getName());
        }
    }

    private function renderShow(Job $job, Application $application, array $applicationErrors, bool $applicationSuccess, string $optInState): ResponseInterface
    {
        $listPid = (int)($this->settings['listPid'] ?? 0);
        $jobPostingJsonLd = $this->buildJobPostingJsonLd($job);
        $this->view->assignMultiple([
            'job' => $job,
            'application' => $application,
            'applicationErrors' => $applicationErrors,
            'applicationSuccess' => $applicationSuccess,
            'optInState' => $optInState,
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

    private function trackListView(): void
    {
        if ($this->isXmlHttpRequest()) {
            return;
        }
        $pageId = $this->getCurrentPageId();
        if ($pageId > 0 && !$this->shouldTrackSessionOnce('aiscareer_list_view_' . $pageId)) {
            return;
        }
        $this->safeAddEvent('list_view', null);
    }

    private function trackDetailView(Job $job): void
    {
        if ($this->isXmlHttpRequest()) {
            return;
        }
        $jobUid = (int)$job->getUid();
        if ($jobUid > 0 && !$this->shouldTrackSessionOnce('aiscareer_detail_view_' . $jobUid)) {
            return;
        }
        $this->safeAddEvent('detail_view', $job);
    }

    private function trackApplicationSubmit(Job $job): void
    {
        $jobUid = (int)$job->getUid();
        if ($jobUid > 0 && !$this->shouldTrackSessionOnce('aiscareer_application_submit_' . $jobUid)) {
            return;
        }
        $this->safeAddEvent('application_submit', $job);
    }

    private function safeAddEvent(string $eventType, ?Job $job): void
    {
        try {
            $jobUid = $job instanceof Job ? (int)$job->getUid() : 0;
            $pid = $this->getCurrentPageId();
            $this->eventRepository->addEvent($eventType, $jobUid, $pid);
        } catch (\Throwable) {
            // avoid blocking frontend if analytics logging fails
        }
    }

    private function isXmlHttpRequest(): bool
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($request instanceof ServerRequestInterface) {
            $header = (string)$request->getHeaderLine('X-Requested-With');
            return strtolower($header) === 'xmlhttprequest';
        }
        return false;
    }

    private function getCurrentPageId(): int
    {
        if (isset($GLOBALS['TSFE']) && $GLOBALS['TSFE'] instanceof \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController) {
            return (int)$GLOBALS['TSFE']->id;
        }
        return 0;
    }

    private function shouldTrackSessionOnce(string $key): bool
    {
        if (!isset($GLOBALS['TSFE']) || !$GLOBALS['TSFE'] instanceof \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController) {
            return true;
        }
        $feUser = $GLOBALS['TSFE']->fe_user ?? null;
        if ($feUser === null) {
            return true;
        }
        try {
            $existing = $feUser->getKey('ses', $key);
            if (!empty($existing)) {
                return false;
            }
            $feUser->setKey('ses', $key, 1);
            $feUser->storeSessionData();
        } catch (\Throwable) {
            return true;
        }
        return true;
    }
}
