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

    /** @var string */
    public $mimeType;
}
