<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use RevisionTen\CQRS\Model\Aggregate;

final class Menu extends Aggregate
{
    public ?string $name = null;

    public ?string $language = null;

    public ?int $website = null;

    public array $items = [];
}
