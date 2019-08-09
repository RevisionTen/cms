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
            $previousAggregate->setModified(new \DateTimeImmutable());
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

        if (
            isset($payload['previousVersion']) &&
            !empty($payload['previousVersion']) &&
            0 !== $aggregate->getVersion()
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
