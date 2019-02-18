<?php

declare(strict_types=1);

namespace RevisionTen\CMS\EventSubscriber;

use RevisionTen\CMS\Services\IndexService;
use RevisionTen\CMS\SymfonyEvent\PagePublishedEvent;
use RevisionTen\CMS\SymfonyEvent\PageUnpublishedEvent;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PageSubscriber implements EventSubscriberInterface
{
    /** @var IndexService $indexService */
    private $indexService;

    /**
     * PageSubscriber constructor.
     *
     * @param IndexService $indexService
     */
    public function __construct(IndexService $indexService)
    {
        $this->indexService = $indexService;
    }

    public static function getSubscribedEvents()
    {
        return [
            PagePublishedEvent::NAME => 'indexPage',
            PageUnpublishedEvent::NAME => 'deindexPage',
        ];
    }

    public function indexPage(PagePublishedEvent $pagePublishedEvent): void
    {
        $output = new NullOutput();
        $this->indexService->index($output, $pagePublishedEvent->getPageUuid());
    }

    public function deindexPage(PageUnpublishedEvent $pageUnpublishedEvent): void
    {
        $output = new NullOutput();
        $this->indexService->index($output, $pageUnpublishedEvent->getPageUuid());
    }
}
