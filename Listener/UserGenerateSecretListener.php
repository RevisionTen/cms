<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Listener;

use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\ListenerInterface;
use RevisionTen\CQRS\Services\CommandBus;

class UserGenerateSecretListener extends UserBaseListener implements ListenerInterface
{
    /**
     * {@inheritdoc}
     */
    public function __invoke(CommandBus $commandBus, EventInterface $event): void
    {
        $userUuid = $event->getCommand()->getAggregateUuid();
        $useMailCodes = $this->config['use_mail_codes'] ?? false;
        if (!$useMailCodes) {
            // Send the secret QRCode via mail.
            $this->userService->sendSecret($userUuid);
        }
    }
}
