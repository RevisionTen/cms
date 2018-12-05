<?php

declare(strict_types=1);

namespace RevisionTen\CMS\EventSubscriber;

use RevisionTen\CMS\Model\Menu;
use RevisionTen\CMS\Services\MenuService;
use RevisionTen\CQRS\Event\AggregateUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MenuSubscriber implements EventSubscriberInterface
{
    /** @var MenuService $menuService */
    private $menuService;

    /**
     * MenuSubscriber constructor.
     *
     * @param MenuService $menuService
     */
    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    public static function getSubscribedEvents()
    {
        return [
            AggregateUpdatedEvent::NAME => 'updateReadModel',
        ];
    }

    public function updateReadModel(AggregateUpdatedEvent $aggregateUpdatedEvent): void
    {
        /** @var \RevisionTen\CQRS\Interfaces\EventInterface $event */
        $event = $aggregateUpdatedEvent->getEvent();

        if ($event->getCommand()->getAggregateClass() === Menu::class) {
            $this->menuService->updateMenuRead($event->getCommand()->getAggregateUuid());
        }
    }
}
