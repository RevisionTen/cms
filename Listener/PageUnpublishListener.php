<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Listener;

use RevisionTen\CMS\SymfonyEvent\PageUnpublishedEvent;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Services\CommandBus;

class PageUnpublishListener extends PageBaseListener
{
    /**
     * {@inheritdoc}
     */
    public function __invoke(CommandBus $commandBus, EventInterface $event): void
    {
        $pageUuid = $event->getCommand()->getAggregateUuid();
        $this->eventDispatcher->dispatch(new PageUnpublishedEvent($pageUuid), PageUnpublishedEvent::NAME);
    }
}
