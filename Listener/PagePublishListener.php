<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Listener;

use RevisionTen\CMS\SymfonyEvent\PagePublishedEvent;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Services\CommandBus;

class PagePublishListener extends PageBaseListener
{
    /**
     * {@inheritdoc}
     */
    public function __invoke(CommandBus $commandBus, EventInterface $event): void
    {
        $pageUuid = $event->getCommand()->getAggregateUuid();
        $version = $event->getCommand()->getOnVersion() + 1;
        $this->eventDispatcher->dispatch(new PagePublishedEvent($pageUuid, $version), PagePublishedEvent::NAME);
    }
}
