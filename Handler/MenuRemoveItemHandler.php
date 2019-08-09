<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Command\MenuRemoveItemCommand;
use RevisionTen\CMS\Event\MenuRemoveItemEvent;
use RevisionTen\CMS\Model\Menu;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use RevisionTen\CQRS\Message\Message;

final class MenuRemoveItemHandler extends MenuBaseHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @param Menu $aggregate
     */
    public function execute(EventInterface $event, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $event->getPayload();

        $uuid = $payload['uuid'];

        // A function that removes a item from its parent.
        $removeAndRebase = static function (&$collection, $uuid) {
            // Remove the item by filtering the items array.
            $collection = array_filter($collection, static function ($item) use ($uuid) {
                return $uuid !== $item['uuid'];
            });

            // Rebase array values.
            $collection = array_values($collection);
        };

        // Remove from root.
        $removeAndRebase($aggregate->items, $uuid);

        // Remove from children.
        $removeItemFunction = static function (&$item, &$collection) use ($removeAndRebase) {
            $removeAndRebase($collection, $item['uuid']);
        };
        self::onItem($aggregate, $uuid, $removeItemFunction);

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new MenuRemoveItemEvent(
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
     * @var Menu $aggregate
     */
    public function validateCommand(CommandInterface $command, AggregateInterface $aggregate): bool
    {
        $payload = $command->getPayload();
        // The uuid to remove.
        $uuid = $payload['uuid'] ?? null;
        $item = \is_string($uuid) ? self::getItem($aggregate, $uuid) : null;

        if (null === $uuid) {
            $this->messageBus->dispatch(new Message(
                'No uuid to remove is set',
                CODE_BAD_REQUEST,
                $command->getUuid(),
                $command->getAggregateUuid()
            ));

            return false;
        } elseif (!$item) {
            $this->messageBus->dispatch(new Message(
                'Item with this uuid was not found',
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
