<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use RevisionTen\CQRS\Model\Aggregate;

final class Page extends Aggregate
{
    public const STATE_PUBLISHED = 'published';
    public const STATE_UNPUBLISHED = 'unpublished';
    public const STATE_STAGED = 'staged';
    public const STATE_SCHEDULED = 'scheduled';
    public const STATE_SCHEDULED_UNPUBLISH = 'scheduled_unpublish';
    public const STATE_DELETED = 'deleted';
    public const STATE_DRAFT = 'draft';

    public ?string $title = null;

    public ?string $language = null;

    public ?int $website = null;

    public ?string $template = null;

    public ?string $description = null;

    public ?string $type = null;

    /*
     * Todo: Use PHP 8 union type string|array|null
     *
     * @var string|array|null $image
     */
    public $image = null;

    public ?array $robots = null;

    public bool $published = false;

    public bool $deleted = false;

    public bool $locked = false;

    public ?array $elements = null;

    public ?array $meta = null;

    public ?string $state = null;

    public ?array $schedule = null;
}
