<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Listener;

use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\ListenerInterface;
use RevisionTen\CQRS\Services\CommandBus;

class UserCreateListener extends UserBaseListener implements ListenerInterface
{
    /**
     * {@inheritdoc}
     */
    public function __invoke(CommandBus $commandBus, EventInterface $event): void
    {
        $payload = $event->getCommand()->getPayload();

        // Update the UserRead Model.
        $userUuid = $event->getCommand()->getAggregateUuid();
        $this->userService->updateUserRead($userUuid);

        $useMailCodes = $this->config['use_mail_codes'] ?? false;
        $migrated = $payload['migrated'] ?? false;
        if (!$useMailCodes && !$migrated) {
            // Send the secret QRCode via mail.
            $this->userService->sendSecret($userUuid);
        }
    }
}
