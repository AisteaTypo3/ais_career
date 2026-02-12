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
        $jobShares = $this->eventRepository->findJobSharesBetween($range['from'], $range['to']);
        $jobFunnel = $this->formatJobFunnel($this->eventRepository->findJobFunnelBetween($range['from'], $range['to']));
        $topJobs = $this->applicationRepository->findTopJobs(5);

        $exportType = $this->resolveExportType();
        if ($exportType !== '') {
            return $this->buildCsvResponse($exportType, $range['from'], $range['to'], $topJobs, $jobFunnel, $jobShares);
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
            'conversionListToDetail' => $this->formatPercent($detailViews, $listViews),
            'conversionDetailToApplication' => $this->formatPercent($applicationSubmits, $detailViews),
            'conversionListToApplication' => $this->formatPercent($applicationSubmits, $listViews),
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
}
