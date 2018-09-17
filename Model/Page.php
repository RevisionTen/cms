<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use RevisionTen\CQRS\Model\Aggregate;

final class Page extends Aggregate
{
    const STATE_PUBLISHED = 'published';
    const STATE_UNPUBLISHED = 'unpublished';
    const STATE_STAGED = 'staged';
    const STATE_DELETED = 'deleted';
    const STATE_DRAFT = 'draft';

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
}
