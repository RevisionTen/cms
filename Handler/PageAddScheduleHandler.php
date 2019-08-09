<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Command\PageAddScheduleCommand;
use RevisionTen\CMS\Event\PageAddScheduleEvent;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use RevisionTen\CQRS\Message\Message;
use RevisionTen\CQRS\Handler\Handler;

final class PageAddScheduleHandler extends Handler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @var Page $aggregate
     */
    public function execute(EventInterface $event, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $event->getPayload();

        $scheduleUuid = $event->getCommandUuid();
        $startDate = $payload['startDate'] ?? null;
        $endDate = $payload['endDate'] ?? null;

        $aggregate->schedule[$scheduleUuid] = [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];

        // Mark page as scheduled.
        if (null !== $startDate && (Page::STATE_UNPUBLISHED === $aggregate->state || Page::STATE_STAGED === $aggregate->state)) {
            $aggregate->state = Page::STATE_SCHEDULED;
        }

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new PageAddScheduleEvent(
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

        if (empty($payload['startDate']) && empty($payload['endDate'])) {
            $this->messageBus->dispatch(new Message(
                'You must chose a time when to publish or unpublish',
                CODE_BAD_REQUEST,
                $command->getUuid(),
                $command->getAggregateUuid()
            ));

            return false;
        }

        return true;
    }
}
