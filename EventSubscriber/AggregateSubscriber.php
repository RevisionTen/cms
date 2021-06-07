<?php

declare(strict_types=1);

namespace RevisionTen\CMS\EventSubscriber;

use Exception;
use RevisionTen\CMS\Model\File;
use RevisionTen\CMS\Model\Menu;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CMS\Model\Role;
use RevisionTen\CMS\Model\UserAggregate;
use RevisionTen\CMS\Services\FileService;
use RevisionTen\CMS\Services\MenuService;
use RevisionTen\CMS\Services\PageService;
use RevisionTen\CMS\Services\RoleService;
use RevisionTen\CMS\Services\UserService;
use RevisionTen\CQRS\Event\AggregateUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AggregateSubscriber implements EventSubscriberInterface
{
    private PageService $pageService;

    private MenuService $menuService;

    private RoleService $roleService;

    private FileService $fileService;

    private UserService $userService;

    public function __construct(PageService $pageService, MenuService $menuService, RoleService $roleService, FileService $fileService, UserService $userService)
    {
        $this->pageService = $pageService;
        $this->menuService = $menuService;
        $this->roleService = $roleService;
        $this->fileService = $fileService;
        $this->userService = $userService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AggregateUpdatedEvent::class => 'updateReadModel',
        ];
    }

    /**
     * @throws Exception
     */
    public function updateReadModel(AggregateUpdatedEvent $aggregateUpdatedEvent): void
    {
        $event = $aggregateUpdatedEvent->getEvent();

        $aggregateClass = $event::getAggregateClass();
        $aggregateUuid = $event->getAggregateUuid();

        if ($aggregateClass === Page::class) {
            $this->pageService->updatePageStreamRead($aggregateUuid);
        } elseif ($aggregateClass === Menu::class) {
            $this->menuService->updateMenuRead($aggregateUuid);
        } elseif ($aggregateClass === Role::class) {
            $this->roleService->updateRoleRead($aggregateUuid);
        } elseif ($aggregateClass === File::class) {
            $this->fileService->updateFileRead($aggregateUuid);
        } elseif ($aggregateClass === UserAggregate::class) {
            $this->userService->updateUserRead($aggregateUuid);
        }
    }
}
