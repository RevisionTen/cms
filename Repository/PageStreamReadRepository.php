<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use function is_array;

class PageStreamReadRepository extends EntityRepository
{
    public function findByQuery($criteria, ?array $sorts = [], ?int $limit = null, ?int $offset = null, ?string $q = '', ?int $websiteId = null)
    {
        $qb = $this->createQueryBuilder('page');

        if (!empty($q)) {
            $qb->where($qb->expr()->like('page.payload', ':q'))->setParameter('q', '%'.$q.'%');
        }

        if (null !== $websiteId) {
            $qb->andWhere($qb->expr()->eq('page.website', ':websiteId'))->setParameter('websiteId', $websiteId);
        }

        if (!empty($criteria)) {
            $qb->addCriteria($criteria);
        }

        if (!empty($sorts) && is_array($sorts)) {
            foreach ($sorts as $sort => $order) {
                $qb->addOrderBy('page.'.$sort, $order);
            }
        }

        $query = $qb->getQuery();

        if (null !== $limit) {
            $query->setMaxResults($limit);
        }
        if (null !== $offset) {
            $query->setFirstResult($offset);
        }

        $paginator = new Paginator($query, $fetchJoinCollection = true);

        return $paginator->getIterator();
    }

    public function countQuery($criteria, ?string $q = '', ?int $websiteId = null)
    {
        $qb = $this->createQueryBuilder('page')->select('count(page.id)');

        if (!empty($q)) {
            $qb->where($qb->expr()->like('page.payload', ':q'))->setParameter('q', '%'.$q.'%');
        }

        if (null !== $websiteId) {
            $qb->andWhere($qb->expr()->eq('page.website', ':websiteId'))->setParameter('websiteId', $websiteId);
        }

        if (!empty($criteria)) {
            $qb->addCriteria($criteria);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }
}
