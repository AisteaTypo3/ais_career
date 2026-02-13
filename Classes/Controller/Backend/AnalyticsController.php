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
        $queryParams = $this->getQueryParams();
        $listViews = $this->eventRepository->countByTypeBetween('list_view', $range['from'], $range['to']);
        $detailViews = $this->eventRepository->countByTypeBetween('detail_view', $range['from'], $range['to']);
        $applicationSubmits = $this->eventRepository->countByTypeBetween('application_submit', $range['from'], $range['to']);
        $shareCopy = $this->eventRepository->countByTypeBetween('share_copy', $range['from'], $range['to']);
        $shareEmail = $this->eventRepository->countByTypeBetween('share_email', $range['from'], $range['to']);
        $shareLinkedin = $this->eventRepository->countByTypeBetween('share_linkedin', $range['from'], $range['to']);
        $shareWhatsapp = $this->eventRepository->countByTypeBetween('share_whatsapp', $range['from'], $range['to']);
        $shareX = $this->eventRepository->countByTypeBetween('share_x', $range['from'], $range['to']);
        $shareTotal = $shareCopy + $shareEmail + $shareLinkedin + $shareWhatsapp + $shareX;
        $shareBreakdown = $this->buildShareBreakdown(
            $shareTotal,
            $shareCopy,
            $shareEmail,
            $shareLinkedin,
            $shareWhatsapp,
            $shareX
        );
        $perPage = 12;
        $topJobsCount = $this->applicationRepository->countTopJobs();
        $jobFunnelCount = $this->eventRepository->countJobFunnelBetween($range['from'], $range['to']);
        $jobSharesCount = $this->eventRepository->countJobSharesBetween($range['from'], $range['to']);

        $topJobsPage = $this->resolvePageFromQuery($queryParams, 'topJobsPage');
        $jobFunnelPage = $this->resolvePageFromQuery($queryParams, 'jobFunnelPage');
        $jobSharesPage = $this->resolvePageFromQuery($queryParams, 'jobSharesPage');

        $topJobsPageData = $this->buildPaginationData($topJobsCount, $perPage, $topJobsPage);
        $jobFunnelPageData = $this->buildPaginationData($jobFunnelCount, $perPage, $jobFunnelPage);
        $jobSharesPageData = $this->buildPaginationData($jobSharesCount, $perPage, $jobSharesPage);

        $jobShares = $this->eventRepository->findJobSharesBetweenPaged(
            $range['from'],
            $range['to'],
            $perPage,
            $jobSharesPageData['offset']
        );
        $jobFunnel = $this->formatJobFunnel($this->eventRepository->findJobFunnelBetweenPaged(
            $range['from'],
            $range['to'],
            $perPage,
            $jobFunnelPageData['offset']
        ));
        $topJobs = $this->applicationRepository->findTopJobsPage($perPage, $topJobsPageData['offset']);

        $exportType = $this->resolveExportType();
        if ($exportType !== '') {
            $allTopJobs = $this->applicationRepository->findTopJobsPage(max(1, $topJobsCount), 0);
            $allJobFunnel = $this->formatJobFunnel($this->eventRepository->findJobFunnelBetweenPaged($range['from'], $range['to'], max(1, $jobFunnelCount), 0));
            $allJobShares = $this->eventRepository->findJobSharesBetweenPaged($range['from'], $range['to'], max(1, $jobSharesCount), 0);

            return $this->buildCsvResponse($exportType, $range['from'], $range['to'], $allTopJobs, $allJobFunnel, $allJobShares);
        }

        $totalJobs = $this->jobRepository->countAll();
        $activeJobs = $this->jobRepository->countActive();
        $totalApplications = $this->applicationRepository->countAll();

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
            'shareTotal' => $shareTotal,
            'shareCopy' => $shareCopy,
            'shareEmail' => $shareEmail,
            'shareLinkedin' => $shareLinkedin,
            'shareWhatsapp' => $shareWhatsapp,
            'shareX' => $shareX,
            'shareBreakdown' => $shareBreakdown,
            'jobShares' => $jobShares,
            'jobSharesPager' => $jobSharesPageData,
            'jobFunnelPager' => $jobFunnelPageData,
            'topJobsPager' => $topJobsPageData,
            'jobSharesPagerUrls' => $this->buildPagerUrls('jobSharesPage', $jobSharesPageData),
            'jobFunnelPagerUrls' => $this->buildPagerUrls('jobFunnelPage', $jobFunnelPageData),
            'topJobsPagerUrls' => $this->buildPagerUrls('topJobsPage', $topJobsPageData),
            'conversionListToDetail' => $this->formatPercent($detailViews, $listViews),
            'conversionDetailToApplication' => $this->formatPercent($applicationSubmits, $detailViews),
            'conversionListToApplication' => $this->formatPercent($applicationSubmits, $listViews),
            'trafficGraph' => $this->buildTrafficGraph($listViews, $detailViews, $applicationSubmits),
            'conversionGraph' => [
                ['key' => 'list_to_detail', 'label' => 'List -> Detail', 'value' => $this->percentValue($detailViews, $listViews)],
                ['key' => 'detail_to_application', 'label' => 'Detail -> Application', 'value' => $this->percentValue($applicationSubmits, $detailViews)],
                ['key' => 'list_to_application', 'label' => 'List -> Application', 'value' => $this->percentValue($applicationSubmits, $listViews)],
            ],
            'period' => $range['period'],
            'fromDate' => $range['from']->format('Y-m-d'),
            'toDate' => $range['to']->format('Y-m-d'),
            'exportUrls' => $this->buildExportUrls($range['period'], $range['from'], $range['to']),
            'queryParams' => $range['queryParams'],
            'jobFunnel' => $jobFunnel,
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
            if (in_array($key, ['period', 'from', 'to', 'export', 'topJobsPage', 'jobFunnelPage', 'jobSharesPage'], true)) {
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

    private function percentValue(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }
        return round(($numerator / $denominator) * 100, 1);
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

    private function resolveExportType(): string
    {
        if ($this->request->hasArgument('export')) {
            return (string)$this->request->getArgument('export');
        }
        $query = $this->getQueryParams();
        return isset($query['export']) ? (string)$query['export'] : '';
    }

    private function buildCsvResponse(
        string $exportType,
        \DateTime $from,
        \DateTime $to,
        array $topJobs,
        array $jobFunnel,
        array $jobShares
    ): ResponseInterface {
        $rows = [];
        $filenamePrefix = 'analytics';

        if ($exportType === 'top_jobs') {
            $filenamePrefix = 'top-jobs';
            $rows[] = ['Job', 'Applications'];
            foreach ($topJobs as $row) {
                $rows[] = [(string)($row['job_title'] ?? ''), (string)($row['applications'] ?? '0')];
            }
        } elseif ($exportType === 'job_funnel') {
            $filenamePrefix = 'job-funnel';
            $rows[] = ['Job', 'Detail views', 'Applications', 'Conversion'];
            foreach ($jobFunnel as $row) {
                $rows[] = [
                    (string)($row['job_title'] ?? ''),
                    (string)($row['detail_views'] ?? '0'),
                    (string)($row['applications'] ?? '0'),
                    (string)($row['conversion'] ?? '0%'),
                ];
            }
        } elseif ($exportType === 'shares_by_job') {
            $filenamePrefix = 'shares-by-job';
            $rows[] = ['Job', 'Total shares', 'Copy link', 'Email', 'LinkedIn', 'WhatsApp', 'X'];
            foreach ($jobShares as $row) {
                $rows[] = [
                    (string)($row['job_title'] ?? ''),
                    (string)($row['shares_total'] ?? '0'),
                    (string)($row['shares_copy'] ?? '0'),
                    (string)($row['shares_email'] ?? '0'),
                    (string)($row['shares_linkedin'] ?? '0'),
                    (string)($row['shares_whatsapp'] ?? '0'),
                    (string)($row['shares_x'] ?? '0'),
                ];
            }
        } else {
            return new \TYPO3\CMS\Core\Http\HtmlResponse('', 400);
        }

        $csvLines = [];
        foreach ($rows as $columns) {
            $escaped = array_map(static fn (string $value): string => '"' . str_replace('"', '""', $value) . '"', $columns);
            $csvLines[] = implode(',', $escaped);
        }
        $csv = implode("\r\n", $csvLines);
        $filename = sprintf(
            '%s_%s_%s.csv',
            $filenamePrefix,
            $from->format('Ymd'),
            $to->format('Ymd')
        );

        return new \TYPO3\CMS\Core\Http\HtmlResponse(
            $csv,
            200,
            [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    /**
     * @return array{sharesByJob:string,topJobs:string,jobFunnel:string}
     */
    private function buildExportUrls(string $period, \DateTime $from, \DateTime $to): array
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof \Psr\Http\Message\ServerRequestInterface) {
            return [
                'sharesByJob' => '',
                'topJobs' => '',
                'jobFunnel' => '',
            ];
        }

        $path = $request->getUri()->getPath();
        $params = (array)$request->getQueryParams();
        unset($params['export']);
        unset($params['topJobsPage'], $params['jobFunnelPage'], $params['jobSharesPage']);
        $params['period'] = $period;
        $params['from'] = $from->format('Y-m-d');
        $params['to'] = $to->format('Y-m-d');

        $build = static function (string $export, string $path, array $params): string {
            $query = $params;
            $query['export'] = $export;
            return $path . '?' . http_build_query($query);
        };

        return [
            'sharesByJob' => $build('shares_by_job', $path, $params),
            'topJobs' => $build('top_jobs', $path, $params),
            'jobFunnel' => $build('job_funnel', $path, $params),
        ];
    }

    /**
     * @return array<int, array{key:string,count:int,percent:float}>
     */
    private function buildShareBreakdown(
        int $total,
        int $copy,
        int $email,
        int $linkedin,
        int $whatsapp,
        int $x
    ): array {
        $rows = [
            ['key' => 'copy', 'count' => $copy],
            ['key' => 'email', 'count' => $email],
            ['key' => 'linkedin', 'count' => $linkedin],
            ['key' => 'whatsapp', 'count' => $whatsapp],
            ['key' => 'x', 'count' => $x],
        ];

        foreach ($rows as $index => $row) {
            $rows[$index]['percent'] = $total > 0
                ? round(($row['count'] / $total) * 100, 1)
                : 0.0;
        }

        usort($rows, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $rows;
    }

    /**
     * @return array<int, array{key:string,label:string,value:int,percent:float}>
     */
    private function buildTrafficGraph(int $listViews, int $detailViews, int $applicationSubmits): array
    {
        $rows = [
            ['key' => 'list', 'label' => 'List views', 'value' => $listViews, 'percent' => 0.0],
            ['key' => 'detail', 'label' => 'Detail views', 'value' => $detailViews, 'percent' => 0.0],
            ['key' => 'applications', 'label' => 'Applications', 'value' => $applicationSubmits, 'percent' => 0.0],
        ];
        $max = max($listViews, $detailViews, $applicationSubmits, 1);
        foreach ($rows as $index => $row) {
            $rows[$index]['percent'] = round(($row['value'] / $max) * 100, 1);
        }
        return $rows;
    }

    private function resolvePageFromQuery(array $queryParams, string $key): int
    {
        $value = $queryParams[$key] ?? 1;
        return max(1, (int)$value);
    }

    /**
     * @return array{currentPage:int,totalPages:int,offset:int,hasPrevious:bool,hasNext:bool,previousPage:int,nextPage:int}
     */
    private function buildPaginationData(int $totalItems, int $perPage, int $requestedPage): array
    {
        $perPage = max(1, $perPage);
        $totalPages = max(1, (int)ceil($totalItems / $perPage));
        $currentPage = min(max(1, $requestedPage), $totalPages);

        return [
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'offset' => ($currentPage - 1) * $perPage,
            'hasPrevious' => $currentPage > 1,
            'hasNext' => $currentPage < $totalPages,
            'previousPage' => max(1, $currentPage - 1),
            'nextPage' => min($totalPages, $currentPage + 1),
        ];
    }

    /**
     * @param array{currentPage:int,totalPages:int,offset:int,hasPrevious:bool,hasNext:bool,previousPage:int,nextPage:int} $pager
     * @return array{previous:string,next:string}
     */
    private function buildPagerUrls(string $pageKey, array $pager): array
    {
        $previous = '';
        $next = '';
        if ($pager['hasPrevious']) {
            $previous = $this->buildModuleUrlWithQuery([$pageKey => $pager['previousPage']]);
        }
        if ($pager['hasNext']) {
            $next = $this->buildModuleUrlWithQuery([$pageKey => $pager['nextPage']]);
        }

        return [
            'previous' => $previous,
            'next' => $next,
        ];
    }

    /**
     * @param array<string, int|string> $overrides
     */
    private function buildModuleUrlWithQuery(array $overrides): string
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof \Psr\Http\Message\ServerRequestInterface) {
            return '';
        }

        $path = $request->getUri()->getPath();
        $params = (array)$request->getQueryParams();
        unset($params['export']);
        foreach ($overrides as $key => $value) {
            $params[$key] = $value;
        }

        return $path . '?' . http_build_query($params);
    }
}
