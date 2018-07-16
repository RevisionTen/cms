<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Command\PageRollbackCommand;
use RevisionTen\CMS\Event\PageRollbackEvent;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use RevisionTen\CQRS\Message\Message;

final class PageRollbackHandler extends PageBaseHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @var Page $aggregate
     */
    public function execute(CommandInterface $command, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $command->getPayload();

        $previousVersion = $payload['previousVersion'];

        if ($this->aggregateFactory) {
            // Build original aggregate and use its state as a starting point.
            /** @var Page $previousAggregate */
            $previousAggregate = $this->aggregateFactory->build($aggregate->getUuid(), Page::class, intval($previousVersion));

            // Override aggregate meta info.
            $previousAggregate->setVersion($aggregate->getVersion());
            $previousAggregate->setStreamVersion($aggregate->getStreamVersion());
            $previousAggregate->setSnapshotVersion($aggregate->getSnapshotVersion());
            $previousAggregate->setModified(new \DateTimeImmutable());
            $previousAggregate->setHistory($aggregate->getHistory());

            $aggregate = $previousAggregate;
        }

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public static function getCommandClass(): string
    {
        return PageRollbackCommand::class;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new PageRollbackEvent($command);
    }

    /**
     * {@inheritdoc}
     */
    public function validateCommand(CommandInterface $command, AggregateInterface $aggregate): bool
    {
        $payload = $command->getPayload();

        if (
            0 !== $aggregate->getVersion() &&
            isset($payload['previousVersion']) &&
            !empty($payload['previousVersion'])
        ) {
            return true;
        } else {
            $this->messageBus->dispatch(new Message(
                'You must provide a previous version',
                CODE_BAD_REQUEST,
                $command->getUuid(),
                $command->getAggregateUuid()
            ));

            return false;
        }
    }
}
