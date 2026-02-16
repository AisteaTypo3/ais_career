<?php

declare(strict_types=1);

namespace Aistea\AisCareer\Domain\Repository;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class JobAlertRepository
{
    private const TABLE = 'tx_aiscareer_domain_model_jobalert';

    public function findOneByEmailAndFilters(string $email, array $filters): ?array
    {
        $qb = $this->qb();
        $row = $qb
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $qb->expr()->eq('deleted', 0),
                $qb->expr()->eq('email', $qb->createNamedParameter($email)),
                $qb->expr()->eq('country', $qb->createNamedParameter((string)($filters['country'] ?? ''))),
                $qb->expr()->eq('department', $qb->createNamedParameter((string)($filters['department'] ?? ''))),
                $qb->expr()->eq('contract_type', $qb->createNamedParameter((string)($filters['contractType'] ?? ''))),
                $qb->expr()->eq('category', $qb->createNamedParameter((int)($filters['category'] ?? 0), ParameterType::INTEGER)),
                $qb->expr()->eq('remote_possible', $qb->createNamedParameter((int)($filters['remotePossible'] ?? -1), ParameterType::INTEGER))
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $row : null;
    }

    public function findOneByDoubleOptInToken(string $token): ?array
    {
        $qb = $this->qb();
        $row = $qb
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $qb->expr()->eq('deleted', 0),
                $qb->expr()->eq('double_opt_in_token', $qb->createNamedParameter($token))
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $row : null;
    }

    public function findOneByUnsubscribeToken(string $token): ?array
    {
        $qb = $this->qb();
        $row = $qb
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $qb->expr()->eq('deleted', 0),
                $qb->expr()->eq('unsubscribe_token', $qb->createNamedParameter($token))
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findConfirmedActiveAlerts(int $limit = 200): array
    {
        $limit = max(1, $limit);
        $qb = $this->qb();

        return $qb
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $qb->expr()->eq('deleted', 0),
                $qb->expr()->eq('hidden', 0),
                $qb->expr()->gt('double_opt_in_confirmed_at', 0),
                $qb->expr()->eq('unsubscribed_at', 0)
            )
            ->orderBy('last_sent_at', 'ASC')
            ->addOrderBy('uid', 'ASC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function create(array $data): int
    {
        $qb = $this->qb();
        $qb->insert(self::TABLE)
            ->values($data)
            ->executeStatement();

        return (int)$qb->getConnection()->lastInsertId(self::TABLE);
    }

    public function updateByUid(int $uid, array $data): void
    {
        if ($uid <= 0 || $data === []) {
            return;
        }

        $qb = $this->qb();
        $qb->update(self::TABLE)
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid, ParameterType::INTEGER)));

        foreach ($data as $field => $value) {
            if (is_int($value)) {
                $qb->set($field, $qb->createNamedParameter($value, ParameterType::INTEGER), false);
            } else {
                $qb->set($field, $qb->createNamedParameter((string)$value), false);
            }
        }

        $qb->executeStatement();
    }

    private function qb(): \TYPO3\CMS\Core\Database\Query\QueryBuilder
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
        $qb->getRestrictions()->removeAll();
        return $qb;
    }
}
