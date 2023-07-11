<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\RequestStack;

class AliasRepository extends ServiceEntityRepository
{
    private ?int $website;

    public function __construct(ManagerRegistry $registry, $entityClass, RequestStack $requestStack)
    {
        parent::__construct($registry, $entityClass);

        $this->website = $requestStack->getMainRequest() ? $requestStack->getMainRequest()->get('currentWebsite') : null;
    }

    /**
     * Filter aliases by currently viewed website.
     */
    public function findAll(): array
    {
        if ($this->website) {
            $qb = $this->createQueryBuilder('a');

            $query = $qb
                ->where($qb->expr()->orX(
                    $qb->expr()->eq('a.website', ':website'),
                    $qb->expr()->isNull('a.website')
                ))
                ->setParameter('website', $this->website)
                ->getQuery();

            return $query->getResult();
        }

        return parent::findAll();
    }

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
            ->andWhere($qb->expr()->eq('a.enabled', ':enabled'))
            ->setParameter('path', $path)
            ->setParameter('website', $website)
            ->setParameter('language', $locale)
            ->setParameter('enabled', true)
            ->setMaxResults(1)
            ->addOrderBy('a.id', 'ASC')
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
            ->andWhere($qb->expr()->eq('a.enabled', ':enabled'))
            ->setParameter('website', $website)
            ->setParameter('language', $locale)
            ->setParameter('enabled', true)
            ->getQuery();

        return $query->getResult();
    }
}
