<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Model\Menu;
use function is_array;

abstract class MenuBaseHandler
{
    /**
     * Checks of the provided item matches or its child items.
     *
     * @param array    $item
     * @param string   $itemUuid
     * @param callable $callable
     * @param array    $collection
     * @param string   $parent
     *
     * @return mixed
     */
    private static function getMatching(array &$item, string $itemUuid, callable $callable = null, array &$collection, $parent = null)
    {
        // Return true if this item is the one we are looking for.
        if (isset($item['uuid']) && $item['uuid'] === $itemUuid) {
            if ($callable) {
                $callable($item, $collection, $parent);
            }

            return $item;
        }

        // Look in child items.
        if (isset($item['items']) && is_array($item['items'])) {
            foreach ($item['items'] as &$subItem) {
                if ($c = self::getMatching($subItem, $itemUuid, $callable, $item['items'], $item)) {
                    return $c;
                }
            }
        }

        return false;
    }

    /**
     * Executes a function on a matching item.
     *
     * @param Menu     $aggregate
     * @param string   $itemUuid
     * @param callable $callable
     */
    public static function onItem(Menu $aggregate, string $itemUuid, callable $callable): void
    {
        foreach ($aggregate->items as &$item) {
            if ($c = self::getMatching($item, $itemUuid, $callable, $aggregate->items, null)) {
                return;
            }
        }
    }

    /**
     * Gets a item by its uuid from the aggregate.
     *
     * @param Menu   $aggregate
     * @param string $itemUuid
     *
     * @return mixed
     */
    public static function getItem(Menu $aggregate, string $itemUuid)
    {
        foreach ($aggregate->items as &$item) {
            if ($c = self::getMatching($item, $itemUuid, null, $aggregate->items, null)) {
                return $c;
            }
        }

        return null;
    }
}
