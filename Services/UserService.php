<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use RevisionTen\CMS\Model\UserAggregate;
use RevisionTen\CMS\Model\UserRead;
use RevisionTen\CQRS\Services\AggregateFactory;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class UserService.
 */
class UserService
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var AggregateFactory
     */
    private $aggregateFactory;

    /**
     * UserService constructor.
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
     * Update the UserRead entity for the admin backend.
     *
     * @param string $userUuid
     */
    public function updateUserRead(string $userUuid): void
    {
        /**
         * @var UserAggregate $aggregate
         */
        $aggregate = $this->aggregateFactory->build($userUuid, UserAggregate::class);

        // Build UserRead entity from Aggregate.
        $userRead = $this->em->getRepository(UserRead::class)->findOneByUuid($userUuid) ?? new UserRead();

        $userRead->setUuid($userUuid);
        $userRead->setEmail($aggregate->email);
        $userRead->setSecret($aggregate->secret);
        $userRead->setAvatarUrl($aggregate->avatarUrl);
        $userRead->setUsername($aggregate->username);
        $userRead->setPassword($aggregate->password);
        $userRead->setColor($aggregate->color);

        // Persist UserRead entity.
        $this->em->persist($userRead);
        $this->em->flush();
    }
}
