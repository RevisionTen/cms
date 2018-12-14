<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use RevisionTen\CMS\Model\Menu;
use RevisionTen\CMS\Model\MenuRead;
use RevisionTen\CQRS\Services\AggregateFactory;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class MenuService.
 */
class MenuService
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var AggregateFactory
     */
    private $aggregateFactory;

    /**
     * @var CacheService
     */
    private $cacheService;

    /**
     * MenuService constructor.
     *
     * @param \Doctrine\ORM\EntityManagerInterface        $entityManager
     * @param \RevisionTen\CQRS\Services\AggregateFactory $aggregateFactory
     * @param \RevisionTen\CMS\Services\CacheService      $cacheService
     */
    public function __construct(EntityManagerInterface $entityManager, AggregateFactory $aggregateFactory, CacheService $cacheService)
    {
        $this->entityManager = $entityManager;
        $this->aggregateFactory = $aggregateFactory;
        $this->cacheService = $cacheService;
    }

    /**
     * Update the MenuRead entity.
     *
     * @param string $menuUuid
     */
    public function updateMenuRead(string $menuUuid): void
    {
        /**
         * @var Menu $aggregate
         */
        $aggregate = $this->aggregateFactory->build($menuUuid, Menu::class);

        // Build MenuRead entity from Aggregate.
        $menuRead = $this->entityManager->getRepository(MenuRead::class)->findOneByUuid($menuUuid) ?? new MenuRead();
        $menuRead->setVersion($aggregate->getStreamVersion());
        $menuRead->setUuid($menuUuid);
        $menuData = json_decode(json_encode($aggregate), true);
        $menuRead->setPayload($menuData);
        $menuRead->setTitle($aggregate->name);
        $menuRead->setWebsite($aggregate->website);
        $menuRead->setLanguage($aggregate->language);

        // Persist MenuRead entity.
        $this->entityManager->persist($menuRead);
        $this->entityManager->flush();

        // Invalidate cache.
        $cacheKey = $aggregate->name.'_'.$aggregate->website.'_'.$aggregate->language;
        $this->cacheService->delete($cacheKey, $aggregate->getStreamVersion() - 1);
    }
}
