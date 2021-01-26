<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Event;

use RevisionTen\CMS\Model\Alias;
use RevisionTen\CMS\Model\Website;
use Symfony\Contracts\EventDispatcher\Event;

final class PageRenderEvent extends Event
{
    public const NAME = 'page.render';

    /**
     * @var array
     */
    public $page;

    /**
     * @var array
     */
    public $config;

    /**
     * @var Website
     */
    public $website;

    /**
     * @var Alias
     */
    public $alias;

    public function __construct(array $page, array $config, ?Website $website = null, ?Alias $alias = null)
    {
        $this->page = $page;
        $this->config = $config;
        $this->website = $website;
        $this->alias = $alias;
    }
}
