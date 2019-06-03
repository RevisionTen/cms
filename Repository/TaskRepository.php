<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * Class TaskRepository
 */
class TaskRepository extends EntityRepository
{
    public function findAllDue(\DateTime $due)
    {
        $qb = $this->createQueryBuilder('task');

        $query = $qb
            ->where($qb->expr()->isNull('task.resultMessage'))
            ->andWhere($qb->expr()->lte('task.due', ':due'))
            ->setParameter('due', $due)
            ->getQuery();

        return $query->getResult();
    }
}
