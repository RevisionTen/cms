<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Command\UserEditCommand;
use RevisionTen\CMS\Event\UserEditEvent;
use RevisionTen\CMS\Model\UserAggregate;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use RevisionTen\CQRS\Message\Message;
use RevisionTen\CQRS\Handler\Handler;

final class UserEditHandler extends Handler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @var UserAggregate $aggregate
     */
    public function execute(CommandInterface $command, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $command->getPayload();

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
    public static function getCommandClass(): string
    {
        return UserEditCommand::class;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new UserEditEvent($command);
    }

    /**
     * {@inheritdoc}
     */
    public function validateCommand(CommandInterface $command, AggregateInterface $aggregate): bool
    {
        $payload = $command->getPayload();

        if (empty($payload)) {
            $this->messageBus->dispatch(new Message(
                'Nothing to update',
                CODE_BAD_REQUEST,
                $command->getUuid(),
                $command->getAggregateUuid()
            ));

            return false;
        }

        return true;
    }
}
