<?php

declare(strict_types=1);

namespace Aistea\AisCareer\Domain\Repository;

use Doctrine\DBAL\ParameterType;
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
}
