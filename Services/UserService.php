<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use RevisionTen\CMS\Model\UserAggregate;
use RevisionTen\CMS\Model\UserRead;
use RevisionTen\CMS\Model\Website;
use RevisionTen\CQRS\Services\AggregateFactory;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class UserService.
 */
class UserService
{
    /** @var EntityManagerInterface */
    private $em;

    /** @var AggregateFactory */
    private $aggregateFactory;

    /** @var SecretService */
    protected $secretService;

    /**
     * UserService constructor.
     *
     * @param EntityManagerInterface $em
     * @param AggregateFactory       $aggregateFactory
     * @param SecretService          $secretService
     */
    public function __construct(EntityManagerInterface $em, AggregateFactory $aggregateFactory, SecretService $secretService)
    {
        $this->em = $em;
        $this->aggregateFactory = $aggregateFactory;
        $this->secretService = $secretService;
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

        // Get collection of websites from their ids.
        $websites = $this->em->getRepository(Website::class)->findBy([
            'id' => $aggregate->websites,
        ]);

        $userRead->setUuid($userUuid);
        $userRead->setVersion($aggregate->getVersion());
        $userRead->setEmail($aggregate->email);
        $userRead->setSecret($aggregate->secret);
        $userRead->setAvatarUrl($aggregate->avatarUrl);
        $userRead->setUsername($aggregate->username);
        $userRead->setPassword($aggregate->password);
        $userRead->setColor($aggregate->color);
        $userRead->setDevices($aggregate->devices);
        $userRead->setIps($aggregate->ips);
        $userRead->setResetToken($aggregate->resetToken);
        $userRead->setWebsites($websites);

        // Persist UserRead entity.
        $this->em->persist($userRead);
        $this->em->flush();
    }

    public function sendSecret(string $userUuid)
    {
        /**
         * @var UserAggregate $aggregate
         */
        $aggregate = $this->aggregateFactory->build($userUuid, UserAggregate::class);

        $this->secretService->sendSecret($aggregate->secret, $aggregate->username, $aggregate->email);
    }

    public function sendLoginInfo(string $userUuid, string $password)
    {
        /**
         * @var UserAggregate $aggregate
         */
        $aggregate = $this->aggregateFactory->build($userUuid, UserAggregate::class);

        $this->secretService->sendLoginInfo($aggregate->username, $password, $aggregate->email);
    }

    public function sendPasswordResetMail(string $userUuid, string $token)
    {
        /**
         * @var UserAggregate $aggregate
         */
        $aggregate = $this->aggregateFactory->build($userUuid, UserAggregate::class);

        $this->secretService->sendPasswordResetMail($aggregate->username, $token, $aggregate->email);
    }
}
