<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use RevisionTen\CMS\Model\Page;
use RevisionTen\CMS\Model\PageRead;
use RevisionTen\CMS\Model\PageStreamRead;
use RevisionTen\CMS\Model\User;
use RevisionTen\CQRS\Model\EventQeueObject;
use RevisionTen\CQRS\Services\AggregateFactory;
use RevisionTen\CQRS\Services\EventBus;
use RevisionTen\CQRS\Services\EventStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;

/**
 * Class PageService.
 */
class PageService
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var AggregateFactory
     */
    private $aggregateFactory;

    /**
     * @var EventStore
     */
    private $eventStore;

    /**
     * @var EventBus
     */
    private $eventBus;

    /**
     * PageService constructor.
     *
     * @param \Doctrine\ORM\EntityManagerInterface   $em
     * @param \RevisionTen\CQRS\Services\AggregateFactory $aggregateFactory
     * @param \RevisionTen\CQRS\Services\EventStore       $eventStore
     * @param \RevisionTen\CQRS\Services\EventBus         $eventBus
     */
    public function __construct(EntityManagerInterface $em, AggregateFactory $aggregateFactory, EventStore $eventStore, EventBus $eventBus)
    {
        $this->em = $em;
        $this->aggregateFactory = $aggregateFactory;
        $this->eventStore = $eventStore;
        $this->eventBus = $eventBus;
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
        $elements = array_filter($elements, function (array $element) {
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
    private function filterPayload(array $payload): array
    {
        if (isset($payload['elements']) && is_array($payload['elements'])) {
            $payload['elements'] = $this->removeDisabled($payload['elements']);
        }

        return $payload;
    }

    /**
     * Publishes a Page.
     *
     * @param string $pageUuid
     * @param int    $version
     */
    public function publishPage(string $pageUuid, int $version): void
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
            if (extension_loaded('apcu') && ini_get('apc.enabled')) {
                $cache = new ApcuAdapter();
                $page = $cache->getItem($pageUuid);
                $page->set($pageData);
                $cache->save($page);
            }
        }

        // Remove all other qeued Events for this Page.
        $this->removeQeuedEvents($pageUuid);

        // Update the PageStreamRead Model.
        $this->updatePageStreamRead($pageUuid);
    }

    /**
     * Unpublishes a Page.
     *
     * @param string $pageUuid
     */
    public function unpublishPage(string $pageUuid): void
    {
        // Remove read model.
        $pageRead = $this->em->getRepository(PageRead::class)->findOneByUuid($pageUuid);
        if ($pageRead) {
            $this->em->remove($pageRead);
            $this->em->flush();
        }

        // Remove from cache.
        if (extension_loaded('apcu') && ini_get('apc.enabled')) {
            $cache = new ApcuAdapter();
            $cache->deleteItem($pageUuid);
        }

        // Remove all other qeued Events for this Page.
        $this->removeQeuedEvents($pageUuid);

        // Update the PageStreamRead Model.
        $this->updatePageStreamRead($pageUuid);
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
        $published = $this->eventBus->publishQeued($eventQeueObjects);

        if ($published) {
            $this->removeQeuedEvents($pageUuid);
        }

        // Update the PageStreamRead Model.
        $this->updatePageStreamRead($pageUuid);
    }

    /**
     * Removes all qeued events of all users for this page.
     *
     * @param string $pageUuid
     */
    private function removeQeuedEvents(string $pageUuid): void
    {
        /** @var User[] $users */
        $users = $this->em->getRepository(User::class)->findAll();

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
        if ($aggregate->deleted) {
            // Deleted pages are always unpublished.
            $pageStream->setPublished(false);
        } else {
            $pageStream->setPublished($aggregate->published);
        }

        // Persist PageStreamRead entity.
        $this->em->persist($pageStream);
        $this->em->flush();
    }
}