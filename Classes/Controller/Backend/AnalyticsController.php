<?php

declare(strict_types=1);

namespace Aistea\AisCareer\Controller\Backend;

use Aistea\AisCareer\Domain\Repository\ApplicationRepository;
use Aistea\AisCareer\Domain\Repository\EventRepository;
use Aistea\AisCareer\Domain\Repository\JobRepository;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class AnalyticsController extends ActionController
{
    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly JobRepository $jobRepository,
        protected readonly ApplicationRepository $applicationRepository,
        protected readonly EventRepository $eventRepository
    ) {
    }

    public function indexAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($GLOBALS['TYPO3_REQUEST']);

        $range = $this->resolveRange();
        $listViews = $this->eventRepository->countByTypeBetween('list_view', $range['from'], $range['to']);
        $detailViews = $this->eventRepository->countByTypeBetween('detail_view', $range['from'], $range['to']);
        $applicationSubmits = $this->eventRepository->countByTypeBetween('application_submit', $range['from'], $range['to']);
        $jobFunnel = $this->eventRepository->findJobFunnelBetween($range['from'], $range['to']);

        $totalJobs = $this->jobRepository->countAll();
        $activeJobs = $this->jobRepository->countActive();
        $totalApplications = $this->applicationRepository->countAll();
        $topJobs = $this->applicationRepository->findTopJobs(5);

        $view = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Fluid\View\StandaloneView::class);
        $view->setTemplatePathAndFilename(
            \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName(
                'EXT:ais_career/Resources/Private/Backend/Templates/Analytics/Index.html'
            )
        );
        $view->assignMultiple([
            'totalJobs' => $totalJobs,
            'activeJobs' => $activeJobs,
            'totalApplications' => $totalApplications,
            'topJobs' => $topJobs,
            'listViews' => $listViews,
            'detailViews' => $detailViews,
            'applicationSubmits' => $applicationSubmits,
            'conversionListToDetail' => $this->formatPercent($detailViews, $listViews),
            'conversionDetailToApplication' => $this->formatPercent($applicationSubmits, $detailViews),
            'conversionListToApplication' => $this->formatPercent($applicationSubmits, $listViews),
            'period' => $range['period'],
            'fromDate' => $range['from']->format('Y-m-d'),
            'toDate' => $range['to']->format('Y-m-d'),
            'queryParams' => $range['queryParams'],
            'jobFunnel' => $this->formatJobFunnel($jobFunnel),
        ]);

        return new \TYPO3\CMS\Core\Http\HtmlResponse($view->render());
    }

    /**
     * @return array{from:\DateTime,to:\DateTime,period:string,queryParams:array<string,mixed>}
     */
    private function resolveRange(): array
    {
        $params = $this->request->getArguments();
        $period = isset($params['period']) ? (string)$params['period'] : '30';
        if (!in_array($period, ['7', '30', '90', 'custom'], true)) {
            $period = '30';
        }

        $now = new \DateTime('now');
        $from = (clone $now)->modify('-30 days');
        $to = (clone $now);
        if ($period === '7') {
            $from = (clone $now)->modify('-7 days');
        } elseif ($period === '90') {
            $from = (clone $now)->modify('-90 days');
        } elseif ($period === 'custom') {
            $fromInput = isset($params['from']) ? (string)$params['from'] : '';
            $toInput = isset($params['to']) ? (string)$params['to'] : '';
            $customFrom = $this->parseDate($fromInput);
            $customTo = $this->parseDate($toInput);
            if ($customFrom instanceof \DateTime && $customTo instanceof \DateTime) {
                $from = $customFrom;
                $to = $customTo;
            } else {
                $period = '30';
            }
        }

        $to->setTime(23, 59, 59);

        return [
            'from' => $from,
            'to' => $to,
            'period' => $period,
            'queryParams' => $this->filterQueryParams($this->getQueryParams()),
        ];
    }

    private function parseDate(string $value): ?\DateTime
    {
        if ($value === '') {
            return null;
        }
        $date = \DateTime::createFromFormat('Y-m-d', $value);
        if ($date === false) {
            return null;
        }
        $date->setTime(0, 0, 0);
        return $date;
    }

    /**
     * @return array<string, mixed>
     */
    private function getQueryParams(): array
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($request instanceof \Psr\Http\Message\ServerRequestInterface) {
            return (array)$request->getQueryParams();
        }
        return [];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, string>
     */
    private function filterQueryParams(array $params): array
    {
        $filtered = [];
        foreach ($params as $key => $value) {
            if (in_array($key, ['period', 'from', 'to'], true)) {
                continue;
            }
            if (is_scalar($value)) {
                $filtered[$key] = (string)$value;
            }
        }
        return $filtered;
    }

    private function formatPercent(int $numerator, int $denominator): string
    {
        if ($denominator <= 0) {
            return '0%';
        }
        $value = ($numerator / $denominator) * 100;
        return number_format($value, 1) . '%';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function formatJobFunnel(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $detailViews = (int)($row['detail_views'] ?? 0);
            $applications = (int)($row['applications'] ?? 0);
            $rows[$index]['conversion'] = $this->formatPercent($applications, $detailViews);
        }
        return $rows;
    }
}
