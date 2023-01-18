<?php

declare(strict_types=1);

namespace RevisionTen\CMS\EventSubscriber;

use RevisionTen\CMS\Event\UserCreateEvent;
use RevisionTen\CMS\Event\UserGenerateSecretEvent;
use RevisionTen\CMS\Event\UserResetPasswordEvent;
use RevisionTen\CMS\Services\UserService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use function is_string;

class UserSubscriber implements EventSubscriberInterface
{
    protected UserService $userService;

    protected array $config;

    public function __construct(UserService $userService, array $config)
    {
        $this->userService = $userService;
        $this->config = $config;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserCreateEvent::class => 'sendSecretAfterCreate',
            UserGenerateSecretEvent::class => 'sendSecret',
            UserResetPasswordEvent::class => 'resetPassword',
        ];
    }

    public function sendSecretAfterCreate(UserCreateEvent $userCreateEvent): void
    {
        $payload = $userCreateEvent->getPayload();
        $migrated = $payload['migrated'] ?? false;

        $userUuid = $userCreateEvent->getAggregateUuid();
        $useMailCodes = $this->config['use_mail_codes'] ?? false;

        if (!$useMailCodes && !$migrated) {
            // Send the secret QRCode via mail.
            try {
                $this->userService->sendSecret($userUuid);
            } catch (TransportExceptionInterface $e) {}
        }
    }

    public function sendSecret(UserGenerateSecretEvent $userGenerateSecretEvent): void
    {
        $userUuid = $userGenerateSecretEvent->getAggregateUuid();
        $useMailCodes = $this->config['use_mail_codes'] ?? false;

        if (!$useMailCodes) {
            // Send the secret QRCode via mail.
            try {
                $this->userService->sendSecret($userUuid);
            } catch (TransportExceptionInterface $e) {}
        }
    }

    public function resetPassword(UserResetPasswordEvent $userResetPasswordEvent): void
    {
        $userUuid = $userResetPasswordEvent->getAggregateUuid();
        // Get the token from the payload.
        $payload = $userResetPasswordEvent->getPayload();
        $token = $payload['token'] ?? null;

        if (is_string($token)) {
            // Send password reset mail.
            try {
                $this->userService->sendPasswordResetMail($userUuid, $token);
            } catch (TransportExceptionInterface $e) {}
        }
    }
}
