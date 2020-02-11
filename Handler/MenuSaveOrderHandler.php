<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Event\MenuSaveOrderEvent;
use RevisionTen\CMS\Model\Menu;
use RevisionTen\CQRS\Exception\CommandValidationException;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use function is_array;

final class MenuSaveOrderHandler extends MenuBaseHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @var Menu $aggregate
     */
    public function execute(EventInterface $event, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $event->getPayload();
        $order = $payload['order'];

        // Get flattened menu.
        $flatMenu = [];
        self::getItems($aggregate->items, $flatMenu);

        // Rebuild menu from order tree.
        $treeMenu = self::fillTree($order, $flatMenu);
        $aggregate->items = $treeMenu;

        // Add leftover items from flattened menu.
        foreach ($flatMenu as $item) {
            $aggregate->items[] = $item;
        }

        return $aggregate;
    }

    /**
     * A function to rebuild the menu from an menu order tree.
     *
     * @param $order
     * @param $flatMenu
     *
     * @return array
     */
    private static function fillTree($order, &$flatMenu): array
    {
        $items = [];

        foreach ($order as $uuid => $childrenOrder) {
            if (isset($flatMenu[$uuid])) {
                $item = $flatMenu[$uuid];

                if (!empty($childrenOrder)) {
                    // Add child items.
                    $item['items'] = self::fillTree($childrenOrder, $flatMenu);
                }

                $items[] = $item;
                unset($flatMenu[$uuid]);
            }
        }

        return $items;
    }

    /**
     * A function to create a flat array of all items in the menu.
     * It traverses the menu and appends all items to the $flatmenu array.
     *
     * @param $items
     * @param $flatMenu
     */
    private static function getItems($items, &$flatMenu): void
    {
        if (empty($items)) {
            return;
        }

        foreach ($items as $item) {
            // Get child items array.
            $childItems = isset($item['items']) && is_array($item['items']) && !empty($item['items']) ? $item['items'] : null;

            if (null !== $childItems) {
                // Unset child items property.
                unset($item['items']);
                // Add parent item to flat menu array.
                $flatMenu[$item['uuid']] = $item;
                // Add the child items.
                self::getItems($childItems, $flatMenu);
            } else {
                // Add parent item to flat menu array.
                $flatMenu[$item['uuid']] = $item;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new MenuSaveOrderEvent(
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
        // The uuid to disable.
        $order = $payload['order'] ?? null;

        if (null === $order) {
            throw new CommandValidationException(
                'No order to save is set',
                CODE_BAD_REQUEST,
                NULL,
                $command
            );
        }

        return true;
    }
}
