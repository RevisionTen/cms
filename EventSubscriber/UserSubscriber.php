<?php

declare(strict_types=1);

namespace RevisionTen\CMS\EventSubscriber;

use RevisionTen\CMS\Event\UserCreateEvent;
use RevisionTen\CMS\Event\UserGenerateSecretEvent;
use RevisionTen\CMS\Event\UserResetPasswordEvent;
use RevisionTen\CMS\Services\UserService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use function is_string;

class UserSubscriber implements EventSubscriberInterface
{
    /** @var UserService */
    protected $userService;

    /** @var array */
    protected $config;

    /**
     * UserSubscriber constructor.
     *
     * @param \RevisionTen\CMS\Services\UserService $userService
     * @param array                                 $config
     */
    public function __construct(UserService $userService, array $config)
    {
        $this->userService = $userService;
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            UserCreateEvent::class => 'sendSecretAfterCreate',
            UserGenerateSecretEvent::class => 'sendSecret',
            UserResetPasswordEvent::class => 'resetPassword',
        ];
    }

    /**
     * @param \RevisionTen\CMS\Event\UserCreateEvent $userCreateEvent
     */
    public function sendSecretAfterCreate(UserCreateEvent $userCreateEvent): void
    {
        $payload = $userCreateEvent->getPayload();
        $migrated = $payload['migrated'] ?? false;

        $userUuid = $userCreateEvent->getAggregateUuid();
        $useMailCodes = $this->config['use_mail_codes'] ?? false;

        if (!$useMailCodes && !$migrated) {
            // Send the secret QRCode via mail.
            $this->userService->sendSecret($userUuid);
        }
    }

    /**
     * @param \RevisionTen\CMS\Event\UserCreateEvent $userCreateEvent
     */
    public function sendSecret(UserCreateEvent $userCreateEvent): void
    {
        $userUuid = $userCreateEvent->getAggregateUuid();
        $useMailCodes = $this->config['use_mail_codes'] ?? false;

        if (!$useMailCodes) {
            // Send the secret QRCode via mail.
            $this->userService->sendSecret($userUuid);
        }
    }

    /**
     * @param \RevisionTen\CMS\Event\UserResetPasswordEvent $userResetPasswordEvent
     */
    public function resetPassword(UserResetPasswordEvent $userResetPasswordEvent): void
    {
        $userUuid = $userResetPasswordEvent->getAggregateUuid();
        // Get the token from the payload.
        $payload = $userResetPasswordEvent->getPayload();
        $token = $payload['token'] ?? null;

        if (null !== $token && is_string($token)) {
            // Send password reset mail.
            $this->userService->sendPasswordResetMail($userUuid, $token);
        }
    }
}
