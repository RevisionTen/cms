<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Listener;

use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\ListenerInterface;
use RevisionTen\CQRS\Services\CommandBus;

class PageSubmitListener extends PageBaseListener implements ListenerInterface
{
    /**
     * {@inheritdoc}
     */
    public function __invoke(CommandBus $commandBus, EventInterface $event): void
    {
        $pageUuid = $event->getCommand()->getAggregateUuid();
        $user = $event->getCommand()->getUser();
        $maxVersion = $event->getCommand()->getOnVersion() + 1;
        $this->pageService->submitPage($pageUuid, $user, $maxVersion);
    }
}
