<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Model\Page;
use function is_array;

abstract class PageBaseHandler
{
    /**
     * Checks of the provided element matches or its child elements.
     *
     * @param array    $element
     * @param string   $elementUuid
     * @param callable $callable
     * @param array    $collection
     * @param string   $parent
     *
     * @return mixed
     */
    private static function getMatching(array &$element, string $elementUuid, callable $callable = null, array &$collection, $parent = null)
    {
        // Return true if this element is the one we are looking for.
        if (isset($element['uuid']) && $element['uuid'] === $elementUuid) {
            if ($callable) {
                $callable($element, $collection, $parent);
            }

            return $element;
        }

        // Look in child elements.
        if (isset($element['elements']) && is_array($element['elements'])) {
            foreach ($element['elements'] as &$subElement) {
                if ($c = self::getMatching($subElement, $elementUuid, $callable, $element['elements'], $element)) {
                    return $c;
                }
            }
        }

        return false;
    }

    /**
     * Executes a function on a matching element.
     *
     * @param Page     $aggregate
     * @param string   $elementUuid
     * @param callable $callable
     */
    public static function onElement(Page $aggregate, string $elementUuid, callable $callable): void
    {
        foreach ($aggregate->elements as &$element) {
            if ($c = self::getMatching($element, $elementUuid, $callable, $aggregate->elements, null)) {
                return;
            }
        }
    }

    /**
     * Gets a element by its uuid from the aggregate.
     *
     * @param Page   $aggregate
     * @param string $elementUuid
     *
     * @return mixed
     */
    public static function getElement(Page $aggregate, string $elementUuid)
    {
        foreach ($aggregate->elements as &$element) {
            if ($c = self::getMatching($element, $elementUuid, null, $aggregate->elements, null)) {
                return $c;
            }
        }

        return null;
    }
}
