<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use Doctrine\ORM\EntityRepository;

/**
 * Class AliasRepository.
 */
class AliasRepository extends EntityRepository
{
    public function findMatchingAlias(string $path, int $website = null, string $locale)
    {
        $qb = $this->createQueryBuilder('a');

        $query = $qb
            ->where('a.path = :path')
            ->andWhere($qb->expr()->orX(
                $qb->expr()->eq('a.website', ':website'),
                $qb->expr()->isNull('a.website')
            ))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->eq('a.language', ':language'),
                $qb->expr()->isNull('a.language')
            ))
            ->setParameter('path', $path)
            ->setParameter('website', $website)
            ->setParameter('language', $locale)
            ->setMaxResults(1)
            ->getQuery();

        return $query->getOneOrNullResult();
    }

    public function findAllMatchingAlias(int $website = null, string $locale)
    {
        $qb = $this->createQueryBuilder('a');

        $query = $qb
            ->where($qb->expr()->orX(
                $qb->expr()->eq('a.website', ':website'),
                $qb->expr()->isNull('a.website')
            ))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->eq('a.language', ':language'),
                $qb->expr()->isNull('a.language')
            ))
            ->setParameter('path', $path)
            ->setParameter('website', $website)
            ->setParameter('language', $locale)
            ->getQuery();

        return $query->getResult();
    }
}
