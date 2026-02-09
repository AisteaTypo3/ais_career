<?php

declare(strict_types=1);

namespace Aistea\AisCareer\Domain\Repository;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\ArrayParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class EventRepository
{
    public function addEvent(string $eventType, int $jobUid = 0, int $pid = 0): void
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_aiscareer_event');
        $now = (int)(new \DateTime())->format('U');

        $qb->insert('tx_aiscareer_event')
            ->values([
                'pid' => $pid,
                'job' => $jobUid,
                'event_type' => $eventType,
                'created_at' => $now,
                'crdate' => $now,
                'tstamp' => $now,
                'deleted' => 0,
            ])
            ->executeStatement();
    }

    public function countByTypeSince(string $eventType, \DateTime $since): int
    {
        $ts = (int)$since->format('U');
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_aiscareer_event');
        $qb->getRestrictions()->removeAll();

        $row = $qb
            ->selectLiteral('COUNT(*) AS cnt')
            ->from('tx_aiscareer_event', 'e')
            ->where(
                $qb->expr()->eq('e.deleted', 0),
                $qb->expr()->eq('e.event_type', $qb->createNamedParameter($eventType)),
                $qb->expr()->gte('e.created_at', $qb->createNamedParameter($ts, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAssociative();

        return (int)($row['cnt'] ?? 0);
    }

    public function countByTypeBetween(string $eventType, \DateTime $from, \DateTime $to): int
    {
        $fromTs = (int)$from->format('U');
        $toTs = (int)$to->format('U');
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_aiscareer_event');
        $qb->getRestrictions()->removeAll();

        $row = $qb
            ->selectLiteral('COUNT(*) AS cnt')
            ->from('tx_aiscareer_event', 'e')
            ->where(
                $qb->expr()->eq('e.deleted', 0),
                $qb->expr()->eq('e.event_type', $qb->createNamedParameter($eventType)),
                $qb->expr()->gte('e.created_at', $qb->createNamedParameter($fromTs, ParameterType::INTEGER)),
                $qb->expr()->lte('e.created_at', $qb->createNamedParameter($toTs, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAssociative();

        return (int)($row['cnt'] ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findJobFunnelBetween(\DateTime $from, \DateTime $to): array
    {
        $fromTs = (int)$from->format('U');
        $toTs = (int)$to->format('U');
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_aiscareer_event');
        $qb->getRestrictions()->removeAll();

        return $qb
            ->select('j.uid AS job_uid', 'j.title AS job_title')
            ->addSelectLiteral('SUM(CASE WHEN e.event_type = \'detail_view\' THEN 1 ELSE 0 END) AS detail_views')
            ->addSelectLiteral('SUM(CASE WHEN e.event_type = \'application_submit\' THEN 1 ELSE 0 END) AS applications')
            ->from('tx_aiscareer_event', 'e')
            ->innerJoin(
                'e',
                'tx_aiscareer_domain_model_job',
                'j',
                $qb->expr()->eq('e.job', $qb->quoteIdentifier('j.uid'))
            )
            ->where(
                $qb->expr()->eq('e.deleted', 0),
                $qb->expr()->eq('j.deleted', 0),
                $qb->expr()->eq('j.hidden', 0),
                $qb->expr()->in('e.event_type', $qb->createNamedParameter(['detail_view', 'application_submit'], ArrayParameterType::STRING)),
                $qb->expr()->gte('e.created_at', $qb->createNamedParameter($fromTs, ParameterType::INTEGER)),
                $qb->expr()->lte('e.created_at', $qb->createNamedParameter($toTs, ParameterType::INTEGER))
            )
            ->groupBy('j.uid', 'j.title')
            ->orderBy('applications', 'DESC')
            ->addOrderBy('detail_views', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
