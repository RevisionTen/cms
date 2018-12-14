<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use RevisionTen\CQRS\Model\Aggregate;

final class Role extends Aggregate
{
    /** @var string */
    public $title;

    /** @var array */
    public $permissions;
}
