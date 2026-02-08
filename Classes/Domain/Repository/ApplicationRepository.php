<?php

declare(strict_types=1);

namespace Aistea\AisCareer\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

class ApplicationRepository extends Repository
{
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
