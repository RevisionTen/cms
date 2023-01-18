<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Event\PageRemoveScheduleEvent;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Exception\CommandValidationException;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;

final class PageRemoveScheduleHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @var Page $aggregate
     */
    public function execute(EventInterface $event, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $event->getPayload();

        $scheduleUuid = $payload['scheduleUuid'];

        unset($aggregate->schedule[$scheduleUuid]);

        // Reset page state.
        if (Page::STATE_SCHEDULED === $aggregate->state || Page::STATE_SCHEDULED_UNPUBLISH === $aggregate->state) {
            $aggregate->state = $aggregate->published ? Page::STATE_PUBLISHED : Page::STATE_UNPUBLISHED;
        }

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new PageRemoveScheduleEvent(
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

        if (empty($payload['scheduleUuid'])) {
            throw new CommandValidationException(
                'You must chose a schedule to remove',
                CODE_BAD_REQUEST,
                NULL,
                $command
            );
        }

        return true;
    }
}
