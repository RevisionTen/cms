<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Repository;

use DateTime;
use Doctrine\ORM\EntityRepository;

class TaskRepository extends EntityRepository
{
    public function findAllDue(DateTime $due)
    {
        $qb = $this->createQueryBuilder('task');

        $query = $qb
            ->where($qb->expr()->isNull('task.resultMessage'))
            ->andWhere($qb->expr()->lte('task.due', ':due'))
            ->andWhere($qb->expr()->neq('task.deleted', ':notDeleted'))
            ->setParameter('due', $due)
            ->setParameter('notDeleted', true)
            ->getQuery();

        return $query->getResult();
    }
}
