<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use ReflectionObject;
use ReflectionProperty;
use RevisionTen\CMS\Event\MenuCreateEvent;
use RevisionTen\CMS\Model\Menu;
use RevisionTen\CQRS\Exception\CommandValidationException;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;

final class MenuCreateHandler extends MenuBaseHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @var Menu $aggregate
     */
    public function execute(EventInterface $event, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $event->getPayload();

        // Change Aggregate state.
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
        return new MenuCreateEvent(
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
        $payload = $command->getPayload();

        if (0 !== $aggregate->getVersion()) {
            throw new CommandValidationException(
                'Aggregate already exists',
                CODE_CONFLICT,
                NULL,
                $command
            );
        }

        if (empty($payload['name']) || empty($payload['website']) || empty($payload['language'])) {
            throw new CommandValidationException(
                'You must enter a name, website and language',
                CODE_BAD_REQUEST,
                NULL,
                $command
            );
        }

        return true;
    }
}
