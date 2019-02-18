<?php

declare(strict_types=1);

namespace RevisionTen\CMS\SymfonyEvent;

class PagePublishedEvent extends \Symfony\Component\EventDispatcher\Event
{
    public const NAME = 'cms.page.published';

    /**
     * @var string
     */
    protected $pageUuid;

    public function __construct(string $pageUuid)
    {
        $this->pageUuid = $pageUuid;
    }

    public function getPageUuid(): string
    {
        return $this->pageUuid;
    }
}
