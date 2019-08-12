<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Event\UserEditEvent;
use RevisionTen\CMS\Model\UserAggregate;
use RevisionTen\CQRS\Exception\CommandValidationException;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;

final class UserEditHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @var UserAggregate $aggregate
     */
    public function execute(EventInterface $event, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $event->getPayload();

        // Update required fields.
        if (!empty($payload['username'])) {
            $aggregate->username = $payload['username'];
        }
        if (!empty($payload['email'])) {
            $aggregate->email = $payload['email'];
        }

        // Update nullable fields.
        $aggregate->avatarUrl = $payload['avatarUrl'] ?? null;
        $aggregate->color = $payload['color'] ?? null;
        $aggregate->websites = $payload['websites'] ?? [];
        $aggregate->roles = $payload['roles'] ?? [];

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new UserEditEvent(
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

        if (empty($payload)) {
            throw new CommandValidationException(
                'Nothing to update',
                CODE_BAD_REQUEST,
                NULL,
                $command
            );
        }

        return true;
    }
}
