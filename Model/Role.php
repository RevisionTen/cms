<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use RevisionTen\CQRS\Model\Aggregate;

final class Role extends Aggregate
{
    public ?string $title = null;

    public array $permissions = [];
}
