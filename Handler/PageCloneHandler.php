<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Command\PageCloneCommand;
use RevisionTen\CMS\Event\PageCloneEvent;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use RevisionTen\CQRS\Message\Message;

final class PageCloneHandler extends PageBaseHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @var Page $aggregate
     */
    public function execute(CommandInterface $command, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $command->getPayload();

        $originalUuid = $payload['originalUuid'];
        $originalVersion = $payload['originalVersion'];

        if ($this->aggregateFactory) {
            // Build original aggregate and use its state as a starting point.
            /** @var Page $originalAggregate */
            $originalAggregate = $this->aggregateFactory->build($originalUuid, Page::class, (int) $originalVersion);
            $baseAggregate = clone $originalAggregate;

            // Override title.
            $baseAggregate->title .= ' duplicate';

            // Override aggregate meta info.
            $baseAggregate->setUuid($aggregate->getUuid());
            $baseAggregate->setVersion($aggregate->getVersion() ?? 1);
            $baseAggregate->setStreamVersion($aggregate->getStreamVersion() ?? 1);
            $baseAggregate->setSnapshotVersion(null);
            $baseAggregate->setCreated(new \DateTimeImmutable());
            $baseAggregate->setModified(new \DateTimeImmutable());
            $baseAggregate->setHistory($aggregate->getHistory());

            $aggregate = $baseAggregate;
        }

        $aggregate->published = false;
        $aggregate->state = Page::STATE_UNPUBLISHED;

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public static function getCommandClass(): string
    {
        return PageCloneCommand::class;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new PageCloneEvent($command);
    }

    /**
     * {@inheritdoc}
     */
    public function validateCommand(CommandInterface $command, AggregateInterface $aggregate): bool
    {
        $payload = $command->getPayload();

        if (
            !empty($payload['originalUuid']) &&
            !empty($payload['originalVersion']) &&
            0 === $aggregate->getVersion()
        ) {
            return true;
        }

        if (0 !== $aggregate->getVersion()) {
            $this->messageBus->dispatch(new Message(
                'Aggregate already exists',
                CODE_CONFLICT,
                $command->getUuid(),
                $command->getAggregateUuid()
            ));

            return false;
        } else {
            $this->messageBus->dispatch(new Message(
                'You must provide an original uuid and version',
                CODE_BAD_REQUEST,
                $command->getUuid(),
                $command->getAggregateUuid()
            ));

            return false;
        }
    }
}
