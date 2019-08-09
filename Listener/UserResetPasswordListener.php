<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Listener;

use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Services\CommandBus;

class UserResetPasswordListener extends UserBaseListener
{
    /**
     * {@inheritdoc}
     */
    public function __invoke(CommandBus $commandBus, EventInterface $event): void
    {
        $userUuid = $event->getCommand()->getAggregateUuid();
        // Get the token from the payload.
        $payload = $event->getCommand()->getPayload();
        $token = $payload['token'] ?? null;

        if (null !== $token && \is_string($token)) {
            // Send password reset mail.
            $this->userService->sendPasswordResetMail($userUuid, $token);
        }
    }
}
