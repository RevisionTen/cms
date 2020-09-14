<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use DateTimeImmutable;
use RevisionTen\CMS\Event\PageCloneEvent;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Exception\CommandValidationException;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use RevisionTen\CQRS\Services\AggregateFactory;

final class PageCloneHandler extends PageBaseHandler implements HandlerInterface
{
    /** @var AggregateFactory */
    private $aggregateFactory;

    /**
     * PageCloneHandler constructor.
     *
     * @param \RevisionTen\CQRS\Services\AggregateFactory $aggregateFactory
     */
    public function __construct(AggregateFactory $aggregateFactory)
    {
        $this->aggregateFactory = $aggregateFactory;
    }

    /**
     * {@inheritdoc}
     *
     * @var Page $aggregate
     * @throws \Exception
     */
    public function execute(EventInterface $event, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $event->getPayload();

        $originalUuid = $payload['originalUuid'];
        $originalVersion = $payload['originalVersion'];

        if ($this->aggregateFactory) {
            // Build original aggregate and use its state as a starting point.
            /** @var Page $originalAggregate */
            $originalAggregate = $this->aggregateFactory->build($originalUuid, Page::class, (int) $originalVersion);
            $baseAggregate = clone $originalAggregate;

            // Override title.
            $baseAggregate->title .= ' duplicate';

            // Do not copy scheduled tasks.
            $baseAggregate->schedule = [];

            // Override aggregate meta info.
            $baseAggregate->setUuid($aggregate->getUuid());
            $baseAggregate->setVersion($aggregate->getVersion() ?? 1);
            $baseAggregate->setStreamVersion($aggregate->getStreamVersion() ?? 1);
            $baseAggregate->setSnapshotVersion(null);
            $baseAggregate->setCreated($aggregate->getCreated());
            $baseAggregate->setModified($aggregate->getModified());
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
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new PageCloneEvent(
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

        if (empty($payload['originalUuid']) || empty($payload['originalVersion'])) {
            throw new CommandValidationException(
                'You must provide an original uuid and version',
                CODE_BAD_REQUEST,
                NULL,
                $command
            );
        }

        return true;
    }
}
