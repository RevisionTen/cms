<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use Exception;
use RevisionTen\CMS\Entity\RoleRead;
use RevisionTen\CMS\Entity\UserRead;
use RevisionTen\CMS\Entity\Website;
use RevisionTen\CMS\Model\UserAggregate;
use RevisionTen\CQRS\Services\AggregateFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class UserService
{
    protected EntityManagerInterface $em;

    protected AggregateFactory $aggregateFactory;

    protected SecretService $secretService;

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
     *
     * @throws Exception
     */
    public function updateUserRead(string $userUuid): void
    {
        /**
         * @var UserAggregate $aggregate
         */
        $aggregate = $this->aggregateFactory->build($userUuid, UserAggregate::class);

        // Clear the EntityManager to avoid inserting duplicate UserRead entities.
        $this->em->clear();

        // Build UserRead entity from Aggregate.
        $userRead = $this->em->getRepository(UserRead::class)->findOneBy(['uuid' => $userUuid]) ?? new UserRead();

        // Get collection of websites from their ids.
        $websites = $this->em->getRepository(Website::class)->findBy([
            'id' => $aggregate->websites,
        ]);

        // Get collection of roles from their uuids.
        $roles = $this->em->getRepository(RoleRead::class)->findBy([
            'uuid' => $aggregate->roles,
        ]);

        $userRead->setUuid($userUuid);
        $userRead->setVersion($aggregate->getVersion());
        $userRead->setEmail($aggregate->email);
        $userRead->setSecret($aggregate->secret);
        $userRead->setAvatarUrl($aggregate->avatarUrl);
        $userRead->setUsername($aggregate->username);
        $userRead->setPassword($aggregate->password);
        $userRead->setColor($aggregate->color);
        $userRead->setTheme($aggregate->theme);
        $userRead->setDevices($aggregate->devices);
        $userRead->setIps($aggregate->ips);
        $userRead->setResetToken($aggregate->resetToken);
        $userRead->setExtra($aggregate->extra);
        $userRead->setWebsites($websites);
        $userRead->setRoles($roles);

        // Persist UserRead entity.
        $this->em->persist($userRead);
        $this->em->flush();
        $this->em->clear();
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendSecret(string $userUuid): void
    {
        /**
         * @var UserAggregate $aggregate
         */
        $aggregate = $this->aggregateFactory->build($userUuid, UserAggregate::class);

        $this->secretService->sendSecret($aggregate->secret, $aggregate->username, $aggregate->email);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendLoginInfo(string $userUuid, string $password): void
    {
        /**
         * @var UserAggregate $aggregate
         */
        $aggregate = $this->aggregateFactory->build($userUuid, UserAggregate::class);

        $this->secretService->sendLoginInfo($aggregate->username, $password, $aggregate->email);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendPasswordResetMail(string $userUuid, string $token): void
    {
        /**
         * @var UserAggregate $aggregate
         */
        $aggregate = $this->aggregateFactory->build($userUuid, UserAggregate::class);

        $this->secretService->sendPasswordResetMail($aggregate->username, $token, $aggregate->email);
    }
}
