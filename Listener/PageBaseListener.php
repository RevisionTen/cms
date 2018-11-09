<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Listener;

use RevisionTen\CMS\Services\PageService;

abstract class PageBaseListener
{
    /** @var PageService */
    protected $pageService;

    /**
     * PageBaseListener constructor.
     *
     * @param PageService $pageService
     */
    public function __construct(PageService $pageService)
    {
        $this->pageService = $pageService;
    }
}
