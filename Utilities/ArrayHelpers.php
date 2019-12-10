<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Utilities;

use function array_key_exists;
use function is_array;
use function json_encode;
use function strcmp;

class ArrayHelpers
{
    /**
     * Returns the difference between base array and change array.
     * Works with multidimensional arrays.
     *
     * @param array $base
     * @param array $change
     *
     * @return array
     */
    public static function diff(array $base, array $change): array
    {
        $diff = [];

        foreach ($change as $property => $value) {
            $equal = true;

            if (!array_key_exists($property, $base)) {
                // Property is new.
                $equal = false;
            } else {
                $originalValue = $base[$property];

                if (is_array($value) && is_array($originalValue)) {
                    // Check if values arrays are identical.
                    if (0 !== strcmp(json_encode($value), json_encode($originalValue))) {
                        // Arrays are not equal.
                        $equal = false;
                    }
                } elseif ($originalValue !== $value) {
                    $equal = false;
                }
            }

            if (!$equal) {
                $diff[$property] = $value;
            }
        }

        return $diff;
    }

    /**
     * @param array $order
     *
     * @return array
     */
    public static function cleanOrderTree(array $order): array
    {
        $orderTree = [];

        foreach ($order as $orderNode) {
            if (isset($orderNode['uuid'])) {
                $orderTree[$orderNode['uuid']] = isset($orderNode['children'][0]) ? self::cleanOrderTree($orderNode['children'][0]) : [];
            }
        }

        return $orderTree;
    }
}
