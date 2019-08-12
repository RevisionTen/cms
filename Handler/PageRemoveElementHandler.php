<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Event\PageRemoveElementEvent;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Exception\CommandValidationException;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use function is_string;

final class PageRemoveElementHandler extends PageBaseHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @param Page $aggregate
     */
    public function execute(EventInterface $event, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $event->getPayload();

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
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new PageRemoveElementEvent(
            $command->getAggregateUuid(),
            $command->getUuid(),
            $command->getOnVersion() + 1,
            $command->getUser(),
            $command->getPayload()
        );
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
        $element = is_string($uuid) ? self::getElement($aggregate, $uuid) : null;

        if (null === $uuid) {
            throw new CommandValidationException(
                'No uuid to remove is set',
                CODE_BAD_REQUEST,
                NULL,
                $command
            );
        }

        if (!$element) {
            throw new CommandValidationException(
                'Element with this uuid was not found',
                CODE_CONFLICT,
                NULL,
                $command
            );
        }

        return true;
    }
}
