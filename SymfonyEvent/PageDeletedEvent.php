<?php

declare(strict_types=1);

namespace RevisionTen\CMS\SymfonyEvent;

class PageDeletedEvent extends \Symfony\Contracts\EventDispatcher\Event
{
    public const NAME = 'cms.page.deleted';

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
