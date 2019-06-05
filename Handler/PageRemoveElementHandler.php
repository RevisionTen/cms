<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Command\PageRemoveElementCommand;
use RevisionTen\CMS\Event\PageRemoveElementEvent;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use RevisionTen\CQRS\Message\Message;

final class PageRemoveElementHandler extends PageBaseHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @param Page $aggregate
     */
    public function execute(CommandInterface $command, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $command->getPayload();

        $uuid = $payload['uuid'];

        // A function that removes a element from its parent.
        $removeAndRebase = static function (&$collection, $uuid) {
            // Remove the element by filtering the elements array.
            $collection = array_filter($collection, static function ($element) use ($uuid) {
                return $uuid !== $element['uuid'];
            });

            // Rebase array values.
            $collection = array_values($collection);
        };

        // Remove from root.
        $removeAndRebase($aggregate->elements, $uuid);

        // Remove from children.
        $removeElementFunction = static function (&$element, &$collection) use ($removeAndRebase) {
            $removeAndRebase($collection, $element['uuid']);
        };
        self::onElement($aggregate, $uuid, $removeElementFunction);

        $aggregate->state = Page::STATE_DRAFT;

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public static function getCommandClass(): string
    {
        return PageRemoveElementCommand::class;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new PageRemoveElementEvent($command);
    }

    /**
     * {@inheritdoc}
     *
     * @var Page $aggregate
     */
    public function validateCommand(CommandInterface $command, AggregateInterface $aggregate): bool
    {
        $payload = $command->getPayload();
        // The uuid to remove.
        $uuid = $payload['uuid'] ?? null;
        $element = \is_string($uuid) ? self::getElement($aggregate, $uuid) : null;

        if (null === $uuid) {
            $this->messageBus->dispatch(new Message(
                'No uuid to remove is set',
                CODE_BAD_REQUEST,
                $command->getUuid(),
                $command->getAggregateUuid()
            ));

            return false;
        } elseif (!$element) {
            $this->messageBus->dispatch(new Message(
                'Element with this uuid was not found',
                CODE_CONFLICT,
                $command->getUuid(),
                $command->getAggregateUuid()
            ));

            return false;
        } else {
            return true;
        }
    }
}
