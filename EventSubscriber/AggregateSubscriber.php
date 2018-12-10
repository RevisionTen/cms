<?php

declare(strict_types=1);

namespace RevisionTen\CMS\EventSubscriber;

use RevisionTen\CMS\Model\File;
use RevisionTen\CMS\Model\Menu;
use RevisionTen\CMS\Model\Role;
use RevisionTen\CMS\Services\FileService;
use RevisionTen\CMS\Services\MenuService;
use RevisionTen\CMS\Services\RoleService;
use RevisionTen\CQRS\Event\AggregateUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AggregateSubscriber implements EventSubscriberInterface
{
    /** @var MenuService $menuService */
    private $menuService;

    /** @var RoleService $roleService */
    private $roleService;

    /** @var FileService $fileService */
    private $fileService;

    /**
     * AggregateSubscriber constructor.
     *
     * @param MenuService $menuService
     * @param RoleService $roleService
     * @param FileService $fileService
     */
    public function __construct(MenuService $menuService, RoleService $roleService, FileService $fileService)
    {
        $this->menuService = $menuService;
        $this->roleService = $roleService;
        $this->fileService = $fileService;
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

        if ($event->getCommand()->getAggregateClass() === Role::class) {
            $this->roleService->updateRoleRead($event->getCommand()->getAggregateUuid());
        }

        if ($event->getCommand()->getAggregateClass() === File::class) {
            $this->fileService->updateFileRead($event->getCommand()->getAggregateUuid());
        }
    }
}
