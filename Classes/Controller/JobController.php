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
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
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

    public function showAction(Job $job, ?Application $application = null, array $applicationErrors = []): ResponseInterface
    {
        $this->addAssets();
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

        $this->view->assignMultiple([
            'job' => $job,
            'application' => $application,
            'applicationErrors' => $applicationErrors,
            'applicationSuccess' => $applicationSuccess,
            'settings' => $this->settings,
        ]);

        return $this->htmlResponse();
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
        if ($errors !== []) {
            $this->addFlashMessage('Please check the application form.', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
            return $this->forward('show', null, null, [
                'job' => $job,
                'application' => $application,
                'applicationErrors' => $errors,
            ]);
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

        if (trim($application->getFirstName()) === '') {
            $errors['firstName'] = 'First name is required.';
        }
        if (trim($application->getLastName()) === '') {
            $errors['lastName'] = 'Last name is required.';
        }
        if (trim($application->getEmail()) === '') {
            $errors['email'] = 'Email is required.';
        } elseif (!GeneralUtility::validEmail($application->getEmail())) {
            $errors['email'] = 'Email is invalid.';
        }
        if (!$application->isConsentPrivacy()) {
            $errors['consentPrivacy'] = 'Privacy consent is required.';
        }

        $fileReference = $application->getCvFile();
        if ($fileReference !== null) {
            $resource = $fileReference->getOriginalResource();
            if ($resource !== null) {
                $file = $resource->getOriginalFile();
                $extension = strtolower((string)$file->getExtension());
                $allowed = array_filter(array_map('trim', explode(',', (string)($this->settings['allowedExtensions'] ?? 'pdf,doc,docx'))));
                if ($allowed !== [] && !in_array($extension, $allowed, true)) {
                    $errors['cvFile'] = 'File type is not allowed.';
                }
                $maxUploadSizeMb = (int)($this->settings['maxUploadSizeMB'] ?? 5);
                $maxUploadSizeBytes = $maxUploadSizeMb * 1024 * 1024;
                if ($file->getSize() > $maxUploadSizeBytes) {
                    $errors['cvFile'] = 'File is too large.';
                }
            }
        }

        return $errors;
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

        $htmlBody = '<h2>New application received</h2>'
            . '<p><strong>Job:</strong> ' . $safe($job->getTitle()) . '</p>'
            . '<p><strong>Reference:</strong> ' . $safe($job->getReference()) . '</p>'
            . '<p><strong>Name:</strong> ' . $safe($application->getFirstName() . ' ' . $application->getLastName()) . '</p>'
            . '<p><strong>Email:</strong> ' . $safe($application->getEmail()) . '</p>'
            . '<p><strong>Phone:</strong> ' . $safe($application->getPhone()) . '</p>'
            . '<p><strong>Message:</strong><br />' . nl2br($safe($application->getMessage())) . '</p>';

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
