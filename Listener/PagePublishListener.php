<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Listener;

use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\ListenerInterface;
use RevisionTen\CQRS\Services\CommandBus;

class PagePublishListener extends PageBaseListener implements ListenerInterface
{
    /**
     * {@inheritdoc}
     */
    public function __invoke(CommandBus $commandBus, EventInterface $event): void
    {
        $payload = $event->getCommand()->getPayload();
        $pageUuid = $event->getCommand()->getAggregateUuid();

        if ($payload && $payload['version']) {
            $version = (int) $payload['version'];

            $this->pageService->publishPage($pageUuid, $version);
        }
    }
}
