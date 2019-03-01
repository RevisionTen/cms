<?php

declare(strict_types=1);

namespace RevisionTen\CMS\EventSubscriber;

use RevisionTen\CMS\Services\IndexService;
use RevisionTen\CMS\Services\PageService;
use RevisionTen\CMS\SymfonyEvent\PagePublishedEvent;
use RevisionTen\CMS\SymfonyEvent\PageUnpublishedEvent;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PageSubscriber implements EventSubscriberInterface
{
    /**
     * @var PageService
     */
    private $pageService;

    /**
     * @var IndexService
     */
    private $indexService;

    /**
     * PageSubscriber constructor.
     *
     * @param PageService  $pageService
     * @param IndexService $indexService
     */
    public function __construct(PageService $pageService, IndexService $indexService)
    {
        $this->pageService = $pageService;
        $this->indexService = $indexService;
    }

    public static function getSubscribedEvents()
    {
        return [
            PagePublishedEvent::NAME => 'updateReadModels',
            PageUnpublishedEvent::NAME => 'deleteReadModels',
        ];
    }

    public function updateReadModels(PagePublishedEvent $pagePublishedEvent): void
    {
        $output = new NullOutput();
        $pageUuid = $pagePublishedEvent->getPageUuid();
        $version = $pagePublishedEvent->getVersion();

        $this->pageService->updatePageStreamRead($pageUuid);
        $this->pageService->updatePageRead($pageUuid, $version);
        $this->indexService->index($output, $pageUuid);
    }

    public function deleteReadModels(PageUnpublishedEvent $pageUnpublishedEvent): void
    {
        $output = new NullOutput();
        $pageUuid = $pageUnpublishedEvent->getPageUuid();

        $this->pageService->updatePageStreamRead($pageUuid);
        $this->pageService->deletePageRead($pageUuid);
        $this->indexService->index($output, $pageUuid);
    }
}
