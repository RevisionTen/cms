<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Utilities;

class ArrayHelpers
{
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
