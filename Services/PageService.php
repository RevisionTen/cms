<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\ORMException;
use Exception;
use Psr\Cache\InvalidArgumentException;
use RevisionTen\CMS\Model\Alias;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CMS\Entity\PageRead;
use RevisionTen\CMS\Entity\PageStreamRead;
use RevisionTen\CMS\Entity\UserRead;
use RevisionTen\CMS\Entity\Website;
use RevisionTen\CQRS\Model\EventQueueObject;
use RevisionTen\CQRS\Services\AggregateFactory;
use RevisionTen\CQRS\Services\EventBus;
use RevisionTen\CQRS\Services\EventStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use function array_filter;
use function array_walk_recursive;
use function count;
use function explode;
use function is_array;
use function json_decode;
use function json_encode;
use function preg_match_all;
use function str_replace;

class PageService
{
    protected EntityManagerInterface $em;

    protected AggregateFactory $aggregateFactory;

    protected EventStore $eventStore;

    protected EventBus $eventBus;

    protected CacheService $cacheService;

    public function __construct(EntityManagerInterface $em, AggregateFactory $aggregateFactory, EventStore $eventStore, EventBus $eventBus, CacheService $cacheService)
    {
        $this->em = $em;
        $this->aggregateFactory = $aggregateFactory;
        $this->eventStore = $eventStore;
        $this->eventBus = $eventBus;
        $this->cacheService = $cacheService;
    }

    /**
     * Get the fully hydrated payload of a published page.
     * Returns the same data that is used when rendering a page.
     *
     * @param string $pageUuid
     *
     * @return array
     * @throws NotFoundHttpException
     */
    public function getPageData(string $pageUuid): array
    {
        $pageRead = $this->em->getRepository(PageRead::class)->findOneBy(['uuid' => $pageUuid]);

        if (null === $pageRead) {
            throw new NotFoundHttpException();
        }

        return $this->hydratePage($pageRead->getPayload());
    }

    /**
     * Get all aliases of a page.
     *
     * @param string $pageUuid
     *
     * @return Collection|null
     */
    public function getAliases(string $pageUuid): ?Collection
    {
        /**
         * @var PageStreamRead|null $pageStreamRead
         */
        $pageStreamRead = $this->em->getRepository(PageStreamRead::class)->findOneBy(['uuid' => $pageUuid]);

        if (null === $pageStreamRead) {
            return null;
        }

        /**
         * @var Collection|null $aliases
         */
        $aliases = $pageStreamRead->getAliases();

        return $aliases;
    }

    /**
     * Get the first alias of the page.
     *
     * @param string $pageUuid
     *
     * @return Alias|null
     */
    public function getFirstAlias(string $pageUuid): ?Alias
    {
        $aliases = $this->getAliases($pageUuid);

        return (null !== $aliases) ? $aliases->first() : null;
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
            if (isset($element['elements']) && is_array($element['elements'])) {
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
        if (isset($payload['elements']) && is_array($payload['elements'])) {
            $payload['elements'] = $this->removeDisabled($payload['elements']);
        }

        return $payload;
    }

    /**
     * Updates all the pages aliases based on the state of the page.
     * This needs to happen after the PageStreamRead has been updated!
     *
     * @param string $pageUuid
     *
     * @throws ORMException
     */
    public function updateAliases(string $pageUuid): void
    {
        /**
         * @var PageStreamRead $pageStreamRead
         */
        $pageStreamRead = $this->em->getRepository(PageStreamRead::class)->findOneBy(['uuid' => $pageUuid]);

        if (null !== $pageStreamRead) {
            $aliases = $pageStreamRead->getAliases();
            if (null !== $aliases) {
                foreach ($aliases as $alias) {
                    /**
                     * @var Alias $alias
                     */
                    // Update status of the alias.
                    $enabled = $pageStreamRead->isPublished() || !empty($alias->getRedirect());
                    $alias->setEnabled($enabled);
                    // Update language and website of the alias.
                    $alias->setLanguage($pageStreamRead->getLanguage());
                    /** @var Website $websiteReference */
                    $websiteReference = $this->em->getReference(Website::class, $pageStreamRead->getWebsite());
                    $alias->setWebsite($websiteReference);

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
     * @throws Exception|InvalidArgumentException
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
            $pageRead = $this->em->getRepository(PageRead::class)->findOneBy(['uuid' => $pageUuid]) ?? new PageRead();
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
     *
     * @throws InvalidArgumentException
     */
    public function deletePageRead(string $pageUuid): void
    {
        // Remove read model.
        /**
         * @var PageRead $pageRead
         */
        $pageRead = $this->em->getRepository(PageRead::class)->findOneBy(['uuid' => $pageUuid]);

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
     * Persists queued events for a specific user and page to the event stream.
     *
     * @param string $pageUuid
     * @param int    $user
     * @param int    $maxVersion the max version for queued events
     *
     * @throws Exception
     */
    public function submitPage(string $pageUuid, int $user, int $maxVersion): void
    {
        /**
         * Find the queued events for this user and page.
         *
         * @var EventQueueObject[] $eventQueueObjects
         */
        $eventQueueObjects = $this->eventStore->findEventObjects(EventQueueObject::class, $pageUuid, $maxVersion, null, $user);

        /**
         * Publish the queued events.
         */
        $this->eventBus->publishQueued($eventQueueObjects);
    }

    /**
     * Removes all queued events of all users for this page.
     *
     * @param string $pageUuid
     */
    private function removeQueuedEvents(string $pageUuid): void
    {
        /**
         * @var UserRead[] $users
         */
        $users = $this->em->getRepository(UserRead::class)->findAll();

        // Remove all other queued Events for this Page.
        foreach ($users as $queueUser) {
            $this->eventStore->discardQueued($pageUuid, $queueUser->getId());
        }
    }

    /**
     * Update the PageStreamRead entity for the admin backend.
     *
     * @param string $pageUuid
     *
     * @throws Exception
     */
    public function updatePageStreamRead(string $pageUuid): void
    {
        /**
         * @var Page $aggregate
         */
        $aggregate = $this->aggregateFactory->build($pageUuid, Page::class);

        // Build PageStreamRead entity from Aggregate.
        $pageStream = $this->em->getRepository(PageStreamRead::class)->findOneBy(['uuid' => $pageUuid]) ?? new PageStreamRead();
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
        $pageStream->setLocked($aggregate->locked);
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

        // Remove old no longer valid queued events.
        $this->removeQueuedEvents($pageUuid);
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
                $entities = $this->em->getRepository($class)->findBy(['id' => $ids]);
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
