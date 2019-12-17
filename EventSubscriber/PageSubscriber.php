<?php

declare(strict_types=1);

namespace RevisionTen\CMS\EventSubscriber;

use DateTime;
use Doctrine\ORM\ORMException;
use Exception;
use RevisionTen\CMS\Command\PagePublishCommand;
use RevisionTen\CMS\Command\PageUnpublishCommand;
use RevisionTen\CMS\Event\PageAddScheduleEvent;
use RevisionTen\CMS\Event\PageDeleteEvent;
use RevisionTen\CMS\Event\PagePublishEvent;
use RevisionTen\CMS\Event\PageRemoveScheduleEvent;
use RevisionTen\CMS\Event\PageSubmitEvent;
use RevisionTen\CMS\Event\PageUnpublishEvent;
use RevisionTen\CMS\Services\IndexService;
use RevisionTen\CMS\Services\PageService;
use RevisionTen\CMS\Services\TaskService;
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
     * @param PageService  $pageService
     * @param IndexService $indexService
     * @param TaskService  $taskService
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
    public static function getSubscribedEvents(): array
    {
        return [
            PagePublishEvent::class => 'updateReadModels',
            PageUnpublishEvent::class => 'deleteReadModels',
            PageDeleteEvent::class => 'deleteReadModelsAndTasks',
            PageSubmitEvent::class => 'submitPage',

            // Todo: These actions should probably only be performed after an
            // aggregate was updated, not when these queued events happen, because
            // if these events are discarded the tasks are orphaned.
            PageAddScheduleEvent::class => 'addSchedule',
            PageRemoveScheduleEvent::class => 'removeSchedule',
        ];
    }

    /**
     * @param PagePublishEvent $pagePublishEvent
     *
     * @throws ORMException
     * @throws Exception
     */
    public function updateReadModels(PagePublishEvent $pagePublishEvent): void
    {
        $output = new NullOutput();
        $pageUuid = $pagePublishEvent->getAggregateUuid();
        $version = $pagePublishEvent->getVersion();

        $this->pageService->updatePageStreamRead($pageUuid);
        $this->pageService->updatePageRead($pageUuid, $version);
        $this->pageService->updateAliases($pageUuid);
        $this->indexService->index($output, $pageUuid);
    }

    /**
     * @param PageUnpublishEvent $pageUnpublishEvent
     *
     * @throws ORMException
     * @throws Exception
     */
    public function deleteReadModels(PageUnpublishEvent $pageUnpublishEvent): void
    {
        $output = new NullOutput();
        $pageUuid = $pageUnpublishEvent->getAggregateUuid();

        $this->pageService->updatePageStreamRead($pageUuid);
        $this->pageService->deletePageRead($pageUuid);
        $this->pageService->updateAliases($pageUuid);
        $this->indexService->index($output, $pageUuid);
    }

    /**
     * @param PageDeleteEvent $pageDeleteEvent
     *
     * @throws ORMException
     * @throws Exception
     */
    public function deleteReadModelsAndTasks(PageDeleteEvent $pageDeleteEvent): void
    {
        $output = new NullOutput();
        $pageUuid = $pageDeleteEvent->getAggregateUuid();

        $this->pageService->updatePageStreamRead($pageUuid);
        $this->pageService->deletePageRead($pageUuid);
        $this->pageService->updateAliases($pageUuid);
        $this->indexService->index($output, $pageUuid);

        $this->taskService->markTasksAsDeleted($pageUuid);
    }

    /**
     * @param PageSubmitEvent $pageSubmitEvent
     *
     * @throws Exception
     */
    public function submitPage(PageSubmitEvent $pageSubmitEvent): void
    {
        $pageUuid = $pageSubmitEvent->getAggregateUuid();
        $user = $pageSubmitEvent->getUser();
        $maxVersion = $pageSubmitEvent->getVersion();

        $this->pageService->submitPage($pageUuid, $user, $maxVersion);
    }

    /**
     * @param PageAddScheduleEvent $pageAddScheduleEvent
     *
     * @throws Exception
     */
    public function addSchedule(PageAddScheduleEvent $pageAddScheduleEvent): void
    {
        $pageUuid = $pageAddScheduleEvent->getAggregateUuid();
        $scheduleUuid = $pageAddScheduleEvent->getCommandUuid();

        $payload = $pageAddScheduleEvent->getPayload();
        $startDate = $payload['startDate'] ?? null;
        $endDate = $payload['endDate'] ?? null;

        // Create scheduler entries.
        if ($startDate) {
            $startDateTime = new DateTime();
            $startDateTime->setTimestamp($startDate);
            $this->taskService->addTask($scheduleUuid, $pageUuid, PagePublishCommand::class, $startDateTime, []);
        }
        if ($endDate) {
            $endDateTime = new DateTime();
            $endDateTime->setTimestamp($endDate);
            $this->taskService->addTask($scheduleUuid, $pageUuid, PageUnpublishCommand::class, $endDateTime, []);
        }
    }

    /**
     * @param PageRemoveScheduleEvent $pageRemoveScheduleEvent
     */
    public function removeSchedule(PageRemoveScheduleEvent $pageRemoveScheduleEvent): void
    {
        $payload = $pageRemoveScheduleEvent->getPayload();

        if (!empty($payload['scheduleUuid'])) {
            $this->taskService->removeTask($payload['scheduleUuid']);
        }
    }
}
