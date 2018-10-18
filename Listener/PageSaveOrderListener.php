<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Listener;

use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\ListenerInterface;
use RevisionTen\CQRS\Services\CommandBus;

class PageSaveOrderListener extends PageBaseListener implements ListenerInterface
{
    /**
     * {@inheritdoc}
     */
    public function __invoke(CommandBus $commandBus, EventInterface $event): void
    {
        // Todo: Qeued events do not need to update pageStreamRead?
        // Update the PageStreamRead Model.
        $pageUuid = $event->getCommand()->getAggregateUuid();
        $this->pageService->updatePageStreamRead($pageUuid);
    }
}
