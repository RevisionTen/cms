<?php

declare(strict_types=1);

namespace RevisionTen\CMS\EventSubscriber;

use RevisionTen\CMS\Services\IndexService;
use RevisionTen\CMS\Services\PageService;
use RevisionTen\CMS\Services\TaskService;
use RevisionTen\CMS\SymfonyEvent\PageDeletedEvent;
use RevisionTen\CMS\SymfonyEvent\PagePublishedEvent;
use RevisionTen\CMS\SymfonyEvent\PageUnpublishedEvent;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PageSubscriber implements EventSubscriberInterface
{
    /**
     * @var PageService
     */
    private $pageService;

    /**
     * @var IndexService
     */
    private $indexService;

    /**
     * @var TaskService
     */
    private $taskService;

    /**
     * PageSubscriber constructor.
     *
     * @param \RevisionTen\CMS\Services\PageService  $pageService
     * @param \RevisionTen\CMS\Services\IndexService $indexService
     * @param \RevisionTen\CMS\Services\TaskService  $taskService
     */
    public function __construct(PageService $pageService, IndexService $indexService, TaskService $taskService)
    {
        $this->pageService = $pageService;
        $this->indexService = $indexService;
        $this->taskService = $taskService;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            PagePublishedEvent::NAME => 'updateReadModels',
            PageUnpublishedEvent::NAME => 'deleteReadModels',
            PageDeletedEvent::NAME => 'deleteTasks',
        ];
    }

    /**
     * @param \RevisionTen\CMS\SymfonyEvent\PagePublishedEvent $pagePublishedEvent
     *
     * @throws \Doctrine\ORM\ORMException
     */
    public function updateReadModels(PagePublishedEvent $pagePublishedEvent): void
    {
        $output = new NullOutput();
        $pageUuid = $pagePublishedEvent->getPageUuid();
        $version = $pagePublishedEvent->getVersion();

        $this->pageService->updatePageStreamRead($pageUuid);
        $this->pageService->updatePageRead($pageUuid, $version);
        $this->pageService->updateAliases($pageUuid);
        $this->indexService->index($output, $pageUuid);
    }

    /**
     * @param \RevisionTen\CMS\SymfonyEvent\PageUnpublishedEvent $pageUnpublishedEvent
     *
     * @throws \Doctrine\ORM\ORMException
     */
    public function deleteReadModels(PageUnpublishedEvent $pageUnpublishedEvent): void
    {
        $output = new NullOutput();
        $pageUuid = $pageUnpublishedEvent->getPageUuid();

        $this->pageService->updatePageStreamRead($pageUuid);
        $this->pageService->deletePageRead($pageUuid);
        $this->pageService->updateAliases($pageUuid);
        $this->indexService->index($output, $pageUuid);
    }

    /**
     * @param \RevisionTen\CMS\SymfonyEvent\PageDeletedEvent $pageDeletedEvent
     */
    public function deleteTasks(PageDeletedEvent $pageDeletedEvent): void
    {
        $pageUuid = $pageDeletedEvent->getPageUuid();

        $this->taskService->markTasksAsDeleted($pageUuid);
    }
}
