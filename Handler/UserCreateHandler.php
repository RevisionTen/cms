<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Event\UserCreateEvent;
use RevisionTen\CMS\Model\UserAggregate;
use RevisionTen\CQRS\Exception\CommandValidationException;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;

final class UserCreateHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @var UserAggregate $aggregate
     */
    public function execute(EventInterface $event, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $event->getPayload();

        $aggregate->username = $payload['username'];
        $aggregate->email = $payload['email'];
        $aggregate->avatarUrl = $payload['avatarUrl'];
        $aggregate->password = $payload['password'];
        $aggregate->secret = $payload['secret'];
        $aggregate->color = $payload['color'];
        $aggregate->websites = $payload['websites'] ?? [];
        $aggregate->roles = $payload['roles'] ?? [];

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new UserCreateEvent(
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

        if (!isset($payload['username'], $payload['email'], $payload['password'], $payload['secret'])) {
            throw new CommandValidationException(
                'Missing user data',
                CODE_BAD_REQUEST,
                NULL,
                $command
            );
        }

        return true;
    }
}
