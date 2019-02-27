<?php

declare(strict_types=1);

namespace RevisionTen\CMS\EventSubscriber;

use RevisionTen\CMS\Model\File;
use RevisionTen\CMS\Model\Menu;
use RevisionTen\CMS\Model\Role;
use RevisionTen\CMS\Model\UserAggregate;
use RevisionTen\CMS\Services\FileService;
use RevisionTen\CMS\Services\MenuService;
use RevisionTen\CMS\Services\RoleService;
use RevisionTen\CMS\Services\UserService;
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

    /** @var UserService $userService */
    private $userService;

    /**
     * AggregateSubscriber constructor.
     *
     * @param MenuService $menuService
     * @param RoleService $roleService
     * @param FileService $fileService
     * @param UserService $userService
     */
    public function __construct(MenuService $menuService, RoleService $roleService, FileService $fileService, UserService $userService)
    {
        $this->menuService = $menuService;
        $this->roleService = $roleService;
        $this->fileService = $fileService;
        $this->userService = $userService;
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

        $aggregateClass = $event->getCommand()->getAggregateClass();
        $aggregateUuid = $event->getCommand()->getAggregateUuid();

        if ($aggregateClass === Menu::class) {
            $this->menuService->updateMenuRead($aggregateUuid);
        } elseif ($aggregateClass === Role::class) {
            $this->roleService->updateRoleRead($aggregateUuid);
        } elseif ($aggregateClass === File::class) {
            $this->fileService->updateFileRead($event->getCommand()->getAggregateUuid());
        } elseif ($aggregateClass === UserAggregate::class) {
            $this->userService->updateUserRead($event->getCommand()->getAggregateUuid());
        }
    }
}
