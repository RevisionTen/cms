<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use RevisionTen\CMS\Model\Role;
use RevisionTen\CMS\Model\RoleRead;
use RevisionTen\CQRS\Services\AggregateFactory;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class RoleService.
 */
class RoleService
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var AggregateFactory
     */
    protected $aggregateFactory;

    /**
     * RoleService constructor.
     *
     * @param \Doctrine\ORM\EntityManagerInterface        $em
     * @param \RevisionTen\CQRS\Services\AggregateFactory $aggregateFactory
     */
    public function __construct(EntityManagerInterface $em, AggregateFactory $aggregateFactory)
    {
        $this->em = $em;
        $this->aggregateFactory = $aggregateFactory;
    }

    /**
     * Update the RoleRead entity.
     *
     * @param string $roleUuid
     */
    public function updateRoleRead(string $roleUuid): void
    {
        /**
         * @var Role $aggregate
         */
        $aggregate = $this->aggregateFactory->build($roleUuid, Role::class);

        // Build RoleRead entity from Aggregate.
        $roleRead = $this->em->getRepository(RoleRead::class)->findOneByUuid($roleUuid) ?? new RoleRead();
        $roleRead->setVersion($aggregate->getStreamVersion());
        $roleRead->setUuid($roleUuid);
        $roleData = json_decode(json_encode($aggregate), true);
        $roleRead->setPayload($roleData);
        $roleRead->setTitle($aggregate->title);
        $roleRead->setPermissions($aggregate->permissions);

        // Persist RoleRead entity.
        $this->em->persist($roleRead);
        $this->em->flush();
    }
}
