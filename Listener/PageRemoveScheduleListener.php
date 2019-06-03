<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Listener;

use RevisionTen\CMS\Services\TaskService;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\ListenerInterface;
use RevisionTen\CQRS\Services\CommandBus;

class PageRemoveScheduleListener implements ListenerInterface
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

        if (!empty($payload['scheduleUuid'])) {
            $this->taskService->removeTask($payload['scheduleUuid']);
        }
    }
}
