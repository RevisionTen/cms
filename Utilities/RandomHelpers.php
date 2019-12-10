<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Utilities;

use Exception;
use function implode;
use function mb_strlen;
use function random_int;

class RandomHelpers
{
    /**
     * @param int    $length
     * @param string $keyspace
     *
     * @return string
     * @throws Exception
     */
    public static function randomString($length = 10, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'): string
    {
        $pieces = [];
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $pieces[] = $keyspace[random_int(0, $max)];
        }

        return implode('', $pieces);
    }
}
