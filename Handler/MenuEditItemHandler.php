<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Command\MenuEditItemCommand;
use RevisionTen\CMS\Event\MenuEditItemEvent;
use RevisionTen\CMS\Model\Menu;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use RevisionTen\CQRS\Message\Message;

final class MenuEditItemHandler extends MenuBaseHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @var Menu $aggregate
     */
    public function execute(EventInterface $event, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $event->getPayload();

        // Add to items.
        $data = $payload['data'];
        $uuid = $payload['uuid'];

        // A function that updates the items data by merging it with the new data.
        $updateDataFunction = static function (&$item, &$collection) use ($data) {
            $item['data'] = array_merge($item['data'], $data);
        };
        self::onItem($aggregate, $uuid, $updateDataFunction);

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new MenuEditItemEvent(
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
        // The uuid to edit.
        $uuid = $payload['uuid'] ?? null;
        $item = \is_string($uuid) ? self::getItem($aggregate, $uuid) : null;

        if (null === $uuid) {
            $this->messageBus->dispatch(new Message(
                'No uuid to edit is set',
                CODE_BAD_REQUEST,
                $command->getUuid(),
                $command->getAggregateUuid()
            ));

            return false;
        } elseif (!isset($payload['data'])) {
            $this->messageBus->dispatch(new Message(
                'No data set',
                CODE_BAD_REQUEST,
                $command->getUuid(),
                $command->getAggregateUuid()
            ));

            return false;
        } elseif (!$item) {
            $this->messageBus->dispatch(new Message(
                'Item with this uuid was not found'.$uuid,
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
