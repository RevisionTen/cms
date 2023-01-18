<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use RevisionTen\CQRS\Model\Aggregate;

final class File extends Aggregate
{
    public ?string $title = null;

    public ?string $path = null;

    public array $oldPaths = [];

    public bool $deleted = false;

    public ?string $mimeType = null;

    public ?int $size = null;

    public ?int $width = null;

    public ?int $height = null;

    public ?string $language = null;

    public ?int $website = null;
}
