<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use ReflectionObject;
use ReflectionProperty;
use RevisionTen\CMS\Event\FileUpdateEvent;
use RevisionTen\CMS\Model\File;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;

final class FileUpdateHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @var File $aggregate
     */
    public function execute(EventInterface $event, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $event->getPayload();

        // Check if file path has changed.
        // Add the old paths to the list of old paths.
        $newPath = $payload['path'] ?? null;
        if ($newPath !== $aggregate->path) {
            $aggregate->oldPaths[] = $aggregate->path;
        }

        // Get each public property from the aggregate and update it If a new value exists in the payload.
        $reflect = new ReflectionObject($aggregate);
        foreach ($reflect->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();
            if (array_key_exists($propertyName, $payload)) {
                $aggregate->{$propertyName} = $payload[$propertyName];
            }
        }

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new FileUpdateEvent(
            $command->getAggregateUuid(),
            $command->getUuid(),
            $command->getOnVersion() + 1,
            $command->getUser(),
            $command->getPayload()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function validateCommand(CommandInterface $command, AggregateInterface $aggregate): bool
    {
        return true;
    }
}
