<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Event\PageLockEvent;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Exception\CommandValidationException;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;

final class PageLockHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @var Page $aggregate
     */
    public function execute(EventInterface $event, AggregateInterface $aggregate): AggregateInterface
    {
        $aggregate->locked = true;

        return $aggregate;
    }

    public function createEvent(CommandInterface $command): EventInterface
    {
        return new PageLockEvent(
            $command->getAggregateUuid(),
            $command->getUuid(),
            $command->getOnVersion() + 1,
            $command->getUser(),
            $command->getPayload()
        );
    }

    /**
     * @param CommandInterface $command
     * @param Page $aggregate
     *
     * @return bool
     * @throws CommandValidationException
     */
    public function validateCommand(CommandInterface $command, AggregateInterface $aggregate): bool
    {
        if ($aggregate->locked) {
            throw new CommandValidationException(
                'Cannot lock already locked page',
                CODE_BAD_REQUEST,
                NULL,
                $command
            );
        }

        return true;
    }
}
