<?php

declare(strict_types=1);

namespace Aistea\AisCareer\Domain\Repository;

use Aistea\AisCareer\Domain\Model\Job;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class JobRepository extends Repository
{
    public function initializeObject(): void
    {
        $this->setDefaultOrderings([
            'sorting' => QueryInterface::ORDER_ASCENDING,
        ]);
    }

    public function findActive(): QueryResultInterface
    {
        $query = $this->createQuery();
        $constraints = $this->buildActiveConstraints($query);
        $query->matching($query->logicalAnd(...$constraints));

        return $query->execute();
    }

    public function findActiveFiltered(array $filters): QueryResultInterface
    {
        $query = $this->createQuery();
        $constraints = $this->buildActiveConstraints($query);

        $country = strtoupper(trim((string)($filters['country'] ?? '')));
        if ($country !== '') {
            $constraints[] = $query->equals('country', $country);
        }

        $department = trim((string)($filters['department'] ?? ''));
        if ($department !== '') {
            $constraints[] = $query->equals('department', $department);
        }

        $contractType = trim((string)($filters['contractType'] ?? ''));
        if ($contractType !== '') {
            $constraints[] = $query->equals('contractType', $contractType);
        }

        if (array_key_exists('remotePossible', $filters) && $filters['remotePossible'] !== '') {
            $constraints[] = $query->equals('remotePossible', (bool)$filters['remotePossible']);
        }

        $categoryUid = (int)($filters['category'] ?? 0);
        if ($categoryUid > 0) {
            $jobUids = $this->findJobUidsForCategory($categoryUid);
            if ($jobUids === []) {
                $constraints[] = $query->equals('uid', 0);
            } else {
                $constraints[] = $query->in('uid', $jobUids);
            }
        }

        if ($constraints !== []) {
            $query->matching($query->logicalAnd(...$constraints));
        }

        return $query->execute();
    }

    public function isJobVisible(Job $job): bool
    {
        if (!$job->isActive()) {
            return false;
        }
        $now = new \DateTime();
        $from = $job->getPublishedFrom();
        $to = $job->getPublishedTo();

        if ($from instanceof \DateTime && $from > $now) {
            return false;
        }
        if ($to instanceof \DateTime && $to < $now) {
            return false;
        }

        return true;
    }

    private function buildActiveConstraints(QueryInterface $query): array
    {
        $now = new \DateTime();
        $nowTs = (int)$now->format('U');

        $constraints = [
            $query->equals('isActive', true),
            $query->logicalOr(
                $query->lessThanOrEqual('publishedFrom', $now),
                $query->lessThanOrEqual('publishedFrom', $nowTs),
                $query->equals('publishedFrom', null),
                $query->equals('publishedFrom', 0)
            ),
            $query->logicalOr(
                $query->greaterThanOrEqual('publishedTo', $now),
                $query->greaterThanOrEqual('publishedTo', $nowTs),
                $query->equals('publishedTo', null),
                $query->equals('publishedTo', 0)
            ),
        ];

        return $constraints;
    }

    private function findJobUidsForCategory(int $categoryUid): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_category_record_mm');

        $rows = $queryBuilder
            ->select('uid_local')
            ->from('sys_category_record_mm')
            ->where(
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($categoryUid, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter('tx_aiscareer_domain_model_job')),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter('categories'))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        return array_values(array_unique(array_map(static fn (array $row): int => (int)$row['uid_local'], $rows)));
    }
}
