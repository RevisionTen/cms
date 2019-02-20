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
    private $cache;

    /**
     * CacheService constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->issuer = $config['site_name'] ?? 'revisionTen';

        if (function_exists('shm_attach') && \extension_loaded('apcu') && ini_get('apc.enabled')) {
            $this->cache = new ApcuAdapter();

            try {
                // Create or get the shared memory segment in which a map of uuids with version numbers is saved.
                $key = (int) ($config['shm_key'] ?? 1);
                $this->shmVarKey = 1;
                // Create a 1MB shared memory segmanet for the UuidStore.
                $this->shmSegment = shm_attach($key, 1000000, 0666);
                $this->initUuidStore();
            } catch (\Exception $exception) {
                // Failed to create the shared memory segment, disable cache.
                $this->cache = null;
            }
        }
    }

    private function initUuidStore(): void
    {
        if (shm_has_var($this->shmSegment, $this->shmVarKey)) {
            // UuidStore exists.
            $this->uuidStore = shm_get_var($this->shmSegment, $this->shmVarKey);
        } elseif (shm_put_var($this->shmSegment, $this->shmVarKey, $this->uuidStore)) {
            // Create UuidStore.
            $this->uuidStore = shm_get_var($this->shmSegment, $this->shmVarKey);
        }
    }

    private function saveUuidStore(): void
    {
        if ($this->shmSegment) {
            shm_put_var($this->shmSegment, $this->shmVarKey, $this->uuidStore);
        }
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
        return $this->uuidStore[$uuid] ?? null;
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

    /**
     * @param string $uuid
     * @param int    $version
     * @param array  $data
     *
     * @return bool|null
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function put(string $uuid, int $version, array $data): ?bool
    {
        if (null === $this->cache) {
            return null;
        }

        // Save current version to memory and return the save version.
        $saveVersion = $this->setVersion($uuid, $version);

        // Save data to apc cache.
        $entry = $this->cache->getItem($this->issuer.'_'.$uuid.'_v'.$saveVersion);
        $entry->set($data);

        return $this->cache->save($entry);
    }

    /**
     * @param string $uuid
     *
     * @return array|null
     * @throws \Psr\Cache\InvalidArgumentException
     */
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

    /**
     * @param string $uuid
     * @param int    $version
     *
     * @return bool|null
     */
    public function delete(string $uuid, int $version): ?bool
    {
        if (null === $this->cache) {
            return null;
        }

        // Delete the version from memory and return the deleted version.
        $deletedVersion = $this->deleteVersion($uuid, $version);

        return $this->cache->deleteItem($this->issuer.'_'.$uuid.'_v'.$deletedVersion);
    }
}
