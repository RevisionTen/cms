<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use RevisionTen\CMS\Model\Page;
use RevisionTen\CMS\Model\PageRead;
use RevisionTen\CMS\Model\PageStreamRead;
use RevisionTen\CMS\Model\UserRead;
use RevisionTen\CMS\Model\Website;
use RevisionTen\CQRS\Model\EventQeueObject;
use RevisionTen\CQRS\Services\AggregateFactory;
use RevisionTen\CQRS\Services\EventBus;
use RevisionTen\CQRS\Services\EventStore;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class PageService.
 */
class PageService
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var AggregateFactory
     */
    protected $aggregateFactory;

    /**
     * @var EventStore
     */
    protected $eventStore;

    /**
     * @var EventBus
     */
    protected $eventBus;

    /**
     * @var CacheService
     */
    protected $cacheService;

    /**
     * PageService constructor.
     *
     * @param \Doctrine\ORM\EntityManagerInterface        $em
     * @param \RevisionTen\CQRS\Services\AggregateFactory $aggregateFactory
     * @param \RevisionTen\CQRS\Services\EventStore       $eventStore
     * @param \RevisionTen\CQRS\Services\EventBus         $eventBus
     * @param \RevisionTen\CMS\Services\CacheService      $cacheService
     */
    public function __construct(EntityManagerInterface $em, AggregateFactory $aggregateFactory, EventStore $eventStore, EventBus $eventBus, CacheService $cacheService)
    {
        $this->em = $em;
        $this->aggregateFactory = $aggregateFactory;
        $this->eventStore = $eventStore;
        $this->eventBus = $eventBus;
        $this->cacheService = $cacheService;
    }

    /**
     * Function to recursively remove disabled elements.
     *
     * @param array $elements
     *
     * @return array
     */
    private function removeDisabled(array $elements): array
    {
        $elements = array_filter($elements, static function (array $element) {
            return $element['enabled'] ?? true;
        });

        foreach ($elements as &$element) {
            if (isset($element['elements']) && \is_array($element['elements'])) {
                $element['elements'] = $this->removeDisabled($element['elements']);
            }
        }

        return $elements;
    }

    /**
     * Filter the payload.
     *
     * @param array $payload
     *
     * @return array
     */
    public function filterPayload(array $payload): array
    {
        if (isset($payload['elements']) && \is_array($payload['elements'])) {
            $payload['elements'] = $this->removeDisabled($payload['elements']);
        }

        return $payload;
    }

    /**
     * Updates all of the pages aliases based on the state of the page.
     * This needs to happen after the PageStreamRead has been updated!
     *
     * @param string $pageUuid
     *
     * @throws \Doctrine\ORM\ORMException
     */
    public function updateAliases(string $pageUuid): void
    {
        /** @var PageStreamRead $pageStreamRead */
        $pageStreamRead = $this->em->getRepository(PageStreamRead::class)->findOneByUuid($pageUuid);

        if (null !== $pageStreamRead) {
            $aliases = $pageStreamRead->getAliases();
            if (null !== $aliases) {
                foreach ($aliases as $alias) {
                    /* @var \RevisionTen\CMS\Model\Alias $alias */
                    // Update status of the alias.
                    $enabled = $pageStreamRead->isPublished() || !empty($alias->getRedirect());
                    $alias->setEnabled($enabled);
                    // Update language and website of the alias.
                    $alias->setLanguage($pageStreamRead->getLanguage());
                    $alias->setWebsite($this->em->getReference(Website::class, $pageStreamRead->getWebsite()));

                    $this->em->persist($alias);
                }
            }
        }

        $this->em->flush();
    }

    /**
     * Updates the PageRead.
     *
     * @param string $pageUuid
     * @param int    $version
     *
     * @throws \Doctrine\ORM\ORMException
     */
    public function updatePageRead(string $pageUuid, int $version): void
    {
        /**
         * Get Aggregate for version.
         *
         * @var Page $aggregate
         */
        $aggregate = $this->aggregateFactory->build($pageUuid, Page::class, $version);

        // Aggregate exists and version matches, persist read model.
        if ($aggregate->getVersion() === $version) {
            $pageRead = $this->em->getRepository(PageRead::class)->findOneByUuid($pageUuid) ?? new PageRead();
            $pageRead->setVersion($version);
            $pageRead->setUuid($pageUuid);
            $pageRead->setWebsite($aggregate->website);

            // Convert aggregate object to payload array and filter it.
            $pageData = json_decode(json_encode($aggregate), true);
            $pageData = $this->filterPayload($pageData);
            $pageRead->setPayload($pageData);

            $this->em->persist($pageRead);
            $this->em->flush();

            // Persist to cache.
            $this->cacheService->put($pageUuid, $version, $pageData);
        }
    }

    /**
     * Delete the PageRead.
     *
     * @param string $pageUuid
     */
    public function deletePageRead(string $pageUuid): void
    {
        // Remove read model.
        /** @var PageRead $pageRead */
        $pageRead = $this->em->getRepository(PageRead::class)->findOneByUuid($pageUuid);

        if ($pageRead) {
            $version = $pageRead->getVersion();

            // Remove from database.
            $this->em->remove($pageRead);
            $this->em->flush();

            // Remove from cache.
            $this->cacheService->delete($pageUuid, $version);
        }
    }

    /**
     * Persists qeued events for a specific user and page to the event stream.
     *
     * @param string $pageUuid
     * @param int    $user
     * @param int    $maxVersion the max version for qeued events
     */
    public function submitPage(string $pageUuid, int $user, int $maxVersion): void
    {
        /**
         * Find the qeued events for this user and page.
         *
         * @var EventQeueObject[] $eventQeueObjects
         */
        $eventQeueObjects = $this->eventStore->findEventObjects(EventQeueObject::class, $pageUuid, $maxVersion, null, $user);

        /**
         * Publish the qeued events.
         */
        $this->eventBus->publishQeued($eventQeueObjects);
    }

    /**
     * Removes all qeued events of all users for this page.
     *
     * @param string $pageUuid
     */
    private function removeQeuedEvents(string $pageUuid): void
    {
        /** @var UserRead[] $users */
        $users = $this->em->getRepository(UserRead::class)->findAll();

        // Remove all other qeued Events for this Page.
        foreach ($users as $qeueUser) {
            $this->eventStore->discardQeued($pageUuid, $qeueUser->getId());
        }
    }

    /**
     * Update the PageStreamRead entity for the admin backend.
     *
     * @param string $pageUuid
     */
    public function updatePageStreamRead(string $pageUuid): void
    {
        /**
         * @var Page $aggregate
         */
        $aggregate = $this->aggregateFactory->build($pageUuid, Page::class);

        // Build PageStreamRead entity from Aggregate.
        $pageStream = $this->em->getRepository(PageStreamRead::class)->findOneByUuid($pageUuid) ?? new PageStreamRead();
        $pageStream->setVersion($aggregate->getStreamVersion());
        $pageStream->setUuid($pageUuid);
        $pageData = json_decode(json_encode($aggregate), true);
        $pageStream->setPayload($pageData);
        $pageStream->setTitle($aggregate->title);
        $pageStream->setLanguage($aggregate->language);
        $pageStream->setTemplate($aggregate->template);
        $pageStream->setCreated($aggregate->created);
        $pageStream->setModified($aggregate->modified);
        $pageStream->setDeleted($aggregate->deleted);
        $pageStream->setWebsite($aggregate->website);
        $pageStream->setState($aggregate->state);
        if ($aggregate->deleted) {
            // Deleted pages are always unpublished.
            $pageStream->setPublished(false);
        } else {
            $pageStream->setPublished($aggregate->published);
        }

        // Persist PageStreamRead entity.
        $this->em->persist($pageStream);
        $this->em->flush();

        // Remove old no longer valid qeued events.
        $this->removeQeuedEvents($pageUuid);
    }

    public function hydratePage(array $pageData): array
    {
        $matches = [];
        preg_match_all('/"doctrineEntity":"(.+)"/U', json_encode($pageData), $matches);
        $hydrationIds = $matches[1] ?? false;

        if ($hydrationIds) {
            $groups = [];
            foreach ($hydrationIds as $hydrationId) {
                $parsedHydrationId = $this->parseHydrationId($hydrationId);
                if (!isset($groups[$parsedHydrationId['class']])) {
                    $groups[$parsedHydrationId['class']] = [];
                }
                $entityIds = explode(',', $parsedHydrationId['id']);
                foreach ($entityIds as $entityId) {
                    $groups[$parsedHydrationId['class']][$entityId] = $entityId;
                }
            }

            // Get doctrine entities.
            foreach ($groups as $class => $ids) {
                $class = str_replace('\\\\', '\\', $class);
                $entities = $this->em->getRepository($class)->findById($ids);
                foreach ($entities as $entity) {
                    $groups[$class][$entity->getId()] = $entity;
                }
            }

            // Replace references in page data with entity objects.
            array_walk_recursive($pageData, function (&$value, $key) use ($groups) {
                if ('doctrineEntity' === $key) {
                    $parsedHydrationId = $this->parseHydrationId($value);
                    $entityIds = explode(',', $parsedHydrationId['id']);
                    $entities = [];
                    foreach ($entityIds as $entityId) {
                        $entities[] = $groups[$parsedHydrationId['class']][$entityId] ?? null;
                    }
                    $value = count($entities) > 1 ? $entities : $entities[0];
                }
            });
        }

        return $pageData;
    }

    /**
     * Explodes a hydrationId string into its id and class components.
     *
     * @param string $hydrationId
     *
     * @return array
     */
    private function parseHydrationId(string $hydrationId): array
    {
        $hydrationIdParts = explode(':', $hydrationId);

        return [
            'class' => $hydrationIdParts[0],
            'id' => $hydrationIdParts[1],
        ];
    }
}
