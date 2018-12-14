<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Listener;

use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\ListenerInterface;
use RevisionTen\CQRS\Services\CommandBus;

class UserEditListener extends UserBaseListener implements ListenerInterface
{
    /**
     * {@inheritdoc}
     */
    public function __invoke(CommandBus $commandBus, EventInterface $event): void
    {
        // Update the UserRead Model.
        $userUuid = $event->getCommand()->getAggregateUuid();
        $this->userService->updateUserRead($userUuid);
    }
}
