<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Listener;

use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\ListenerInterface;
use RevisionTen\CQRS\Services\CommandBus;

class UserResetPasswordListener extends UserBaseListener implements ListenerInterface
{
    /**
     * {@inheritdoc}
     */
    public function __invoke(CommandBus $commandBus, EventInterface $event): void
    {
        // Update the UserRead Model.
        $userUuid = $event->getCommand()->getAggregateUuid();
        $this->userService->updateUserRead($userUuid);

        // Get the token from the payload.
        $payload = $event->getCommand()->getPayload();
        $token = $payload['token'] ?? null;

        if (null !== $token) {
            // Send password reset mail.
            $this->userService->sendPasswordResetMail($userUuid, $token);
        }
    }
}
