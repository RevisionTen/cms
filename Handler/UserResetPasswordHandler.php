<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Command\UserResetPasswordCommand;
use RevisionTen\CMS\Event\UserResetPasswordEvent;
use RevisionTen\CMS\Model\UserAggregate;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use RevisionTen\CQRS\Message\Message;
use RevisionTen\CQRS\Handler\Handler;

final class UserResetPasswordHandler extends Handler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @var UserAggregate $aggregate
     */
    public function execute(CommandInterface $command, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $command->getPayload();

        $aggregate->resetToken = $payload['token'];

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public static function getCommandClass(): string
    {
        return UserResetPasswordCommand::class;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new UserResetPasswordEvent($command);
    }

    /**
     * {@inheritdoc}
     */
    public function validateCommand(CommandInterface $command, AggregateInterface $aggregate): bool
    {
        $payload = $command->getPayload();

        if (!isset($payload['token'])) {
            $this->messageBus->dispatch(new Message(
                'Missing token',
                CODE_BAD_REQUEST,
                $command->getUuid(),
                $command->getAggregateUuid()
            ));

            return false;
        }

        return true;
    }
}
