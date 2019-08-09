<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Listener;

use RevisionTen\CMS\Command\PagePublishCommand;
use RevisionTen\CMS\Command\PageUnpublishCommand;
use RevisionTen\CMS\Services\TaskService;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Services\CommandBus;

class PageAddScheduleListener
{
    /** @var TaskService */
    protected $taskService;

    /**
     * PageAddScheduleListener constructor.
     *
     * @param TaskService $taskService
     */
    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(CommandBus $commandBus, EventInterface $event): void
    {
        $payload = $event->getCommand()->getPayload();

        $pageUuid = $event->getCommand()->getAggregateUuid();
        $scheduleUuid = $event->getCommand()->getUuid();
        $startDate = $payload['startDate'] ?? null;
        $endDate = $payload['endDate'] ?? null;

        // Create scheduler entries.
        if ($startDate) {
            $startDateTime = new \DateTime();
            $startDateTime->setTimestamp($startDate);
            $this->taskService->addTask($scheduleUuid, $pageUuid, PagePublishCommand::class, $startDateTime, []);
        }
        if ($endDate) {
            $endDateTime = new \DateTime();
            $endDateTime->setTimestamp($endDate);
            $this->taskService->addTask($scheduleUuid, $pageUuid, PageUnpublishCommand::class, $endDateTime, []);
        }
    }
}
