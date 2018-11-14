<?php

declare(strict_types=1);

namespace RevisionTen\CMS\EventSubscriber;

use RevisionTen\CQRS\Event\AggregateUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MenuSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            AggregateUpdatedEvent::NAME => 'updateReadModel',
        ];
    }

    public function updateReadModel(AggregateUpdatedEvent $event): void
    {
        #/** @var \RevisionTen\CQRS\Interfaces\EventInterface $event */
        #$event = $event->getEvent();

        // Todo: Implement MenuRead model and update it here.
    }
}
