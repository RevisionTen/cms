<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use RevisionTen\CQRS\Model\Aggregate;

final class Menu extends Aggregate
{
    /** @var string */
    public $name;

    /** @var array */
    public $items;
}
