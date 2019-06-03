<?php

declare(strict_types=1);

namespace RevisionTen\CMS\SymfonyEvent;

class PagePublishedEvent extends \Symfony\Contracts\EventDispatcher\Event
{
    public const NAME = 'cms.page.published';

    /**
     * @var string
     */
    protected $pageUuid;

    /**
     * @var int
     */
    protected $version;

    public function __construct(string $pageUuid, int $version)
    {
        $this->pageUuid = $pageUuid;
        $this->version = $version;
    }

    public function getPageUuid(): string
    {
        return $this->pageUuid;
    }

    public function getVersion(): int
    {
        return $this->version;
    }
}
