<?php

declare(strict_types=1);

namespace Aistea\AisCareer\Domain\Repository;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

class ApplicationRepository extends Repository
{
    public function countAll(): int
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_aiscareer_domain_model_application');
        $qb->getRestrictions()->removeAll();
        $row = $qb
            ->selectLiteral('COUNT(*) AS cnt')
            ->from('tx_aiscareer_domain_model_application', 'a')
            ->where(
                $qb->expr()->eq('a.deleted', 0),
                $qb->expr()->eq('a.hidden', 0)
            )
            ->executeQuery()
            ->fetchAssociative();
        return (int)($row['cnt'] ?? 0);
    }

    public function countSince(\DateTime $since): int
    {
        $ts = (int)$since->format('U');
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_aiscareer_domain_model_application');
        $qb->getRestrictions()->removeAll();
        $row = $qb
            ->selectLiteral('COUNT(*) AS cnt')
            ->from('tx_aiscareer_domain_model_application', 'a')
            ->where(
                $qb->expr()->eq('a.deleted', 0),
                $qb->expr()->eq('a.hidden', 0),
                $qb->expr()->gte('a.created_at', $qb->createNamedParameter($ts, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAssociative();
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findTopJobs(int $limit): array
    {
        $limit = max(1, $limit);
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_aiscareer_domain_model_application');
        $qb->getRestrictions()->removeAll();

        return $qb
            ->select('j.uid AS job_uid', 'j.title AS job_title')
            ->addSelectLiteral('COUNT(a.uid) AS applications')
            ->from('tx_aiscareer_domain_model_application', 'a')
            ->innerJoin(
                'a',
                'tx_aiscareer_domain_model_job',
                'j',
                $qb->expr()->eq('a.job', $qb->quoteIdentifier('j.uid'))
            )
            ->where(
                $qb->expr()->eq('a.deleted', 0),
                $qb->expr()->eq('a.hidden', 0),
                $qb->expr()->eq('j.deleted', 0),
                $qb->expr()->eq('j.hidden', 0)
            )
            ->groupBy('j.uid', 'j.title')
            ->orderBy('applications', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return \TYPO3\CMS\Extbase\Persistence\QueryResultInterface<\Aistea\AisCareer\Domain\Model\Application>
     */
    public function findOlderThan(\DateTime $cutoff)
    {
        $query = $this->createQuery();
        $ts = (int)$cutoff->format('U');
        $query->matching(
            $query->logicalOr(
                $query->lessThan('createdAt', $cutoff),
                $query->lessThan('createdAt', $ts)
            )
        );
        return $query->execute();
    }
}
