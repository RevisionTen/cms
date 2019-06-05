<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use RevisionTen\CMS\Model\Task;
use RevisionTen\CQRS\Model\EventStreamObject;
use RevisionTen\CQRS\Services\CommandBus;
use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CQRS\Services\MessageBus;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class TaskService.
 */
class TaskService
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var CommandBus
     */
    protected $commandBus;

    /**
     * @var CommandBus
     */
    protected $messageBus;

    /**
     * TaskService constructor.
     *
     * @param EntityManagerInterface  $em
     * @param CommandBus              $commandBus
     * @param MessageBus              $messageBus
     */
    public function __construct(EntityManagerInterface $em, CommandBus $commandBus, MessageBus $messageBus)
    {
        $this->em = $em;
        $this->commandBus = $commandBus;
        $this->messageBus = $messageBus;
    }

    public function addTask(string $uuid, string $aggregateUuid, string $command, \DateTime $due, array $payload): void
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

    public function removeTask(string $uuid): void
    {
        /** @var Task[] $tasks */
        $tasks = $this->em->getRepository(Task::class)->findBy(['uuid' => $uuid]);

        foreach ($tasks as $task) {
            $this->em->remove($task);
        }

        $this->em->flush();
        $this->em->clear();
    }

    public function runTasks(OutputInterface $output): void
    {
        $due = new \DateTime();

        /** @var Task[] $tasks */
        $tasks = $this->em->getRepository(Task::class)->findAllDue($due);

        foreach ($tasks as $task) {
            $commandClass = $task->getCommand();
            $aggregateUuid = $task->getAggregateUuid();
            $payload = $task->getPayload();

            // Get current aggregate version.
            /** @var EventStreamObject[]|null $lastEventOnAggregate */
            $lastEventOnAggregate = $this->em->getRepository(EventStreamObject::class)->findBy(['uuid' => $aggregateUuid], ['version' => 'DESC'], 1);
            if (!empty($lastEventOnAggregate[0])) {
                $onVersion = $lastEventOnAggregate[0]->getVersion();
            } else {
                $onVersion = null;
            }

            if (null !== $onVersion) {
                $success = false;
                $successCallback = static function ($commandBus, $event) use (&$success) { $success = true; };

                $command = new $commandClass(-1, null, $aggregateUuid, $onVersion, $payload, $successCallback);
                $this->commandBus->dispatch($command);

                // Save messages on task.
                $messages = $this->messageBus->getMessages();
                $this->messageBus->clear();
                $task->setResultMessage($messages);
                $this->em->persist($task);
                $this->em->flush();

                $output->writeln('Executed task '.$commandClass.' on '.$aggregateUuid);
            }
        }

        $this->em->clear();
    }
}
