<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use Symfony\Component\Cache\Adapter\ApcuAdapter;

/**
 * Class CacheService.
 */
class CacheService
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var string
     */
    private $issuer;

    /**
     * @var resource
     */
    private $shmSegment;

    /**
     * @var int
     */
    private $shmVarKey;

    /**
     * @var array
     */
    private $uuidStore = [];

    /**
     * @var null|ApcuAdapter
     */
    private $cache = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->issuer = isset($config['site_name']) ? $config['site_name'] : 'revisionTen';

        // Create or get the shared memory segment in which a map of uuids with version numbers is saved.
        $key = isset($config['shm_key']) ? $config['shm_key'] : 1;
        $key = (int) $key;
        $memsize = 2000000; // Reserve 2MB for UuidStore.
        $this->shmSegment = shm_attach($key, $memsize, 0666);
        $this->shmVarKey = 1;

        $this->initUuidStore();

        if (extension_loaded('apcu') && ini_get('apc.enabled')) {
            $this->cache = new ApcuAdapter();
        }
    }

    private function initUuidStore(): void
    {
        if (shm_has_var($this->shmSegment, $this->shmVarKey)) {
            // UuidStore exists.
            $this->uuidStore = shm_get_var($this->shmSegment, $this->shmVarKey);
        } else {
            // Create UuidStore.
            if (shm_put_var($this->shmSegment, $this->shmVarKey, $this->uuidStore)) {
                $this->uuidStore = shm_get_var($this->shmSegment, $this->shmVarKey);
            }
        }
    }

    private function saveUuidStore(): void
    {
        shm_put_var($this->shmSegment, $this->shmVarKey, $this->uuidStore);
    }

    /**
     * Save the version for this uuid in memory and return the version.
     *
     * @param string $uuid
     * @param int    $version
     *
     * @return int|null
     */
    private function setVersion(string $uuid, int $version): ?int
    {
        $this->uuidStore[$uuid] = $version;

        $this->saveUuidStore();

        return $version;
    }

    /**
     * Get the version for this uuid from memory.
     *
     * @param string $uuid
     *
     * @return int|null
     */
    private function getVersion(string $uuid): ?int
    {
        $version = $this->uuidStore[$uuid] ?? null;

        return $version;
    }

    /**
     * Delete the version for this uuid in memory and return the deleted version.
     *
     * @param string $uuid
     * @param int    $version
     *
     * @return int|null
     */
    private function deleteVersion(string $uuid, int $version): ?int
    {
        if (isset($this->uuidStore[$uuid])) {
            // Todo: Optional check if version matches.
            $version = $this->uuidStore[$uuid];
            unset($this->uuidStore[$uuid]);

            $this->saveUuidStore();
        }

        return $version;
    }

    public function put(string $uuid, int $version, array $data): ?bool
    {
        if (null === $this->cache) {
            return null;
        }

        // Save current version to memory and return the save version.
        $version = $this->setVersion($uuid, $version);

        // Save data to apc cache.
        $entry = $this->cache->getItem($this->issuer.'_'.$uuid.'_v'.$version);
        $entry->set($data);

        return $this->cache->save($entry);
    }

    public function get(string $uuid): ?array
    {
        if (null === $this->cache) {
            return null;
        }

        $data = null;

        // Get current version from memory.
        $version = $this->getVersion($uuid);

        if ($version) {
            // Get data from apc cache.
            $entry = $this->cache->getItem($this->issuer.'_'.$uuid.'_v'.$version);

            if ($entry->isHit()) {
                $data = $entry->get();
            }
        }

        return $data;
    }

    public function delete(string $uuid, int $version): ?bool
    {
        // Delete the version from memory and return the deleted version.
        $version = $this->deleteVersion($uuid, $version);

        return $this->cache->deleteItem($this->issuer.'_'.$uuid.'_v'.$version);
    }
}