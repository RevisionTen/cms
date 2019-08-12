<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Event\MenuAddItemEvent;
use RevisionTen\CMS\Model\Menu;
use RevisionTen\CQRS\Exception\CommandValidationException;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;

final class MenuAddItemHandler extends MenuBaseHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @var Menu $aggregate
     */
    public function execute(EventInterface $event, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $event->getPayload();
        $itemName = $payload['itemName'];
        $data = $payload['data'];

        // Build item data.
        $newItem = [
            'uuid' => $event->getCommandUuid(),
            'itemName' => $itemName,
            'data' => $data,
            'enabled' => true,
        ];

        // Add to items.
        $parentUuid = $payload['parent'] ?? null;

        if ($parentUuid && \is_string($parentUuid)) {
            // A function that add the new item to the target parent.
            $addItemFunction = static function (&$item, &$collection) use ($newItem) {
                if (!isset($item['items'])) {
                    $item['items'] = [];
                }
                $item['items'][] = $newItem;
            };
            self::onItem($aggregate, $parentUuid, $addItemFunction);
        } else {
            // Add to menu root.
            $aggregate->items[] = $newItem;
        }

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new MenuAddItemEvent(
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

        if (!isset($payload['itemName'])) {
            throw new CommandValidationException(
                'No item type set',
                CODE_BAD_REQUEST,
                NULL,
                $command
            );
        }

        if (!isset($payload['data'])) {
            throw new CommandValidationException(
                'No data set',
                CODE_BAD_REQUEST,
                NULL,
                $command
            );
        }

        return true;
    }
}
