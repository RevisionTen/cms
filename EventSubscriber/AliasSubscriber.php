<?php

declare(strict_types=1);

namespace RevisionTen\CMS\EventSubscriber;

use RevisionTen\CMS\Model\Alias;
use RevisionTen\CMS\Model\PageStreamRead;
use RevisionTen\CMS\Services\IndexService;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Console\Output\BufferedOutput;

class AliasSubscriber implements EventSubscriber
{
    private IndexService $indexService;

    public function __construct(IndexService $indexService)
    {
        $this->indexService = $indexService;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postUpdate,
            Events::postPersist,
            Events::postRemove,
        ];
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Alias && null !== $entity->getPageStreamRead()) {
            $this->indexPage($entity->getPageStreamRead());
        }
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Alias && null !== $entity->getPageStreamRead()) {
            $this->indexPage($entity->getPageStreamRead());
        }
    }

    public function postRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Alias && null !== $entity->getPageStreamRead()) {
            $this->indexPage($entity->getPageStreamRead());
        }
    }

    private function indexPage(PageStreamRead $pageStreamRead): void
    {
        $output = new BufferedOutput();
        $this->indexService->index($output, $pageStreamRead->getUuid());
    }
}
