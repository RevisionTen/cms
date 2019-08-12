<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use DateTimeImmutable;
use RevisionTen\CMS\Event\PageRollbackEvent;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Exception\CommandValidationException;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use RevisionTen\CQRS\Services\AggregateFactory;

final class PageRollbackHandler extends PageBaseHandler implements HandlerInterface
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
        $user = $event->getUser();

        $previousVersion = $payload['previousVersion'];

        if ($this->aggregateFactory) {
            // Build original aggregate and use its state as a starting point.
            /** @var Page $previousAggregate */
            $previousAggregate = $this->aggregateFactory->build($aggregate->getUuid(), Page::class, (int) $previousVersion, $user);

            // Override aggregate meta info.
            $previousAggregate->setVersion($aggregate->getVersion());
            $previousAggregate->setStreamVersion($aggregate->getStreamVersion());
            $previousAggregate->setSnapshotVersion($aggregate->getSnapshotVersion());
            $previousAggregate->setModified(new DateTimeImmutable());
            $previousAggregate->setHistory($aggregate->getHistory());

            $aggregate = $previousAggregate;
        }

        $aggregate->state = Page::STATE_DRAFT;

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new PageRollbackEvent(
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

        if (0 === $aggregate->getVersion()) {
            throw new CommandValidationException(
                'You cannot rollback an aggregate with no version',
                CODE_BAD_REQUEST,
                NULL,
                $command
            );
        }

        if (empty($payload['previousVersion'])) {
            throw new CommandValidationException(
                'You must provide a previous version',
                CODE_BAD_REQUEST,
                NULL,
                $command
            );
        }

        return true;
    }
}
