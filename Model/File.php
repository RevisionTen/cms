<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use RevisionTen\CQRS\Model\Aggregate;

final class File extends Aggregate
{
    /** @var string */
    public $title;

    /** @var string */
    public $path;

    /** @var string|null */
    public $mimeType;

    /** @var int|null */
    public $size;

    /** @var int|null */
    public $width;

    /** @var int|null */
    public $height;

    /** @var string */
    public $language;

    /** @var int */
    public $website;
}
