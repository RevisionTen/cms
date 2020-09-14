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

    /** @var string */
    public $title;

    /** @var string */
    public $language;

    /** @var int */
    public $website;

    /** @var string */
    public $template;

    /** @var string */
    public $description;

    /** @var string */
    public $type;

    /** @var string */
    public $image;

    /** @var array */
    public $robots;

    /** @var bool */
    public $published = false;

    /** @var bool */
    public $deleted = false;

    /** @var array */
    public $elements;

    /** @var array */
    public $meta;

    /** @var string */
    public $state;

    /** @var array */
    public $schedule;
}
