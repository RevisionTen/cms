<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use DateTime;
use Exception;
use RevisionTen\CMS\Command\PageRemoveScheduleCommand;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CMS\Model\Task;
use RevisionTen\CQRS\Model\EventStreamObject;
use RevisionTen\CQRS\Services\CommandBus;
use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CQRS\Services\MessageBus;
use Symfony\Component\Console\Output\OutputInterface;

class TaskService
{
    protected EntityManagerInterface $em;

    protected CommandBus $commandBus;

    protected MessageBus $messageBus;

    public function __construct(EntityManagerInterface $em, CommandBus $commandBus, MessageBus $messageBus)
    {
        $this->em = $em;
        $this->commandBus = $commandBus;
        $this->messageBus = $messageBus;
    }

    public function addTask(string $uuid, string $aggregateUuid, string $command, DateTime $due, array $payload): void
    {
        $task = new Task();

        $task->setUuid($uuid);
        $task->setAggregateUuid($aggregateUuid);
        $task->setCommand($command);
        $task->setDue($due);
        $task->setPayload($payload);

        $this->em->persist($task);
        $this->em->flush();
    }

    public function markTasksAsDeleted(string $aggregateUuid): void
    {
        /**
         * @var Task[] $tasks
         */
        $tasks = $this->em->getRepository(Task::class)->findBy(['aggregateUuid' => $aggregateUuid]);

        foreach ($tasks as $task) {
            $task->setDeleted(true);
            $this->em->persist($task);
        }

        $this->em->flush();
        $this->em->clear();
    }

    public function removeTask(string $taskUuid): void
    {
        /**
         * @var Task[] $tasks
         */
        $tasks = $this->em->getRepository(Task::class)->findBy(['uuid' => $taskUuid]);

        foreach ($tasks as $task) {
            if (null !== $task->getResultMessage()) {
                // Task was executed, mark as deleted but keep it.
                $task->setDeleted(true);
                $this->em->persist($task);
            } else {
                // Task was never executed.
                $this->em->remove($task);
            }
        }

        $this->em->flush();
        $this->em->clear();
    }

    /**
     * @param OutputInterface $output
     *
     * @throws Exception
     */
    public function runTasks(OutputInterface $output): void
    {
        $due = new DateTime();

        /**
         * @var Task[] $tasks
         */
        $tasks = $this->em->getRepository(Task::class)->findAllDue($due);

        foreach ($tasks as $task) {
            // Skip deleted tasks.
            $isDeleted = $task->getDeleted();
            if ($isDeleted) {
                continue;
            }

            $taskUuid = $task->getUuid();
            $commandClass = $task->getCommand();
            $aggregateUuid = $task->getAggregateUuid();
            $payload = $task->getPayload();

            // Get current aggregate version.
            /**
             * @var EventStreamObject[]|null $lastEventOnAggregate
             */
            $lastEventOnAggregate = $this->em->getRepository(EventStreamObject::class)->findBy(['uuid' => $aggregateUuid], ['version' => 'DESC'], 1);
            if (!empty($lastEventOnAggregate[0])) {
                $onVersion = $lastEventOnAggregate[0]->getVersion();
                $aggregateClass = $lastEventOnAggregate[0]->getAggregateClass();
            } else {
                $onVersion = null;
                $aggregateClass = null;
            }

            if (null !== $onVersion && null !== $aggregateClass) {
                $success = $this->commandBus->execute(
                    $commandClass,
                    $aggregateUuid,
                    $payload,
                    -1
                );

                // Save messages on task.
                $messages = $this->messageBus->getMessages();
                $this->messageBus->clear();
                $task->setResultMessage($messages);
                $this->em->persist($task);
                $this->em->flush();

                $output->writeln('Executed task '.$commandClass.' on '.$aggregateUuid);

                // Remove task from page.
                if ($success && $aggregateClass === Page::class) {
                    $command = new PageRemoveScheduleCommand(-1, null, $aggregateUuid, ($onVersion+1), [
                        'scheduleUuid' => $taskUuid,
                    ]);
                    $this->commandBus->dispatch($command);
                }
            }
        }

        $this->em->clear();
    }
}
