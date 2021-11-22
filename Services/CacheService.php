<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use function extension_loaded;
use function function_exists;
use function ini_get;
use function is_bool;

// These functions might not exist and should therefore not be imported,
// even though this would not cause an error.
// requires ext-sysvshm and ext-apcu
#use function shm_attach;
#use function shm_get_var;
#use function shm_has_var;
#use function shm_put_var;

/**
 * Class CacheService.
 */
class CacheService
{
    private ?string $issuer;

    /**
     * @var resource
     */
    private $shmSegment;

    private ?int $shmVarKey = null;

    private array $uuidStore = [];

    private ?ApcuAdapter $cache = null;

    private bool $disableCacheWorkaround;

    /**
     * CacheService constructor.
     *
     * @param array $config
     *
     * @throws InvalidArgumentException
     */
    public function __construct(array $config)
    {
        $apcEnabled = extension_loaded('apcu') && ini_get('apc.enabled');

        $this->issuer = $config['site_name'] ?? 'revisionTen';
        $this->disableCacheWorkaround = (bool) ($config['disable_cache_workaround'] ?? false);

        if ($apcEnabled && $this->disableCacheWorkaround) {
            $this->cache = new ApcuAdapter();
            $this->initUuidStore();
        } elseif ($apcEnabled && function_exists('shm_attach')) {
            $this->cache = new ApcuAdapter();

            try {
                // Create or get the shared memory segment in which a map of uuids with version numbers is saved.
                $key = (int) ($config['shm_key'] ?? 1);
                $this->shmVarKey = 1;
                // Create a 1 MB shared memory segment for the UuidStore.
                $this->shmSegment = shm_attach($key, 1000000);
                $this->initUuidStore();
            } catch (Exception $exception) {
                // Failed to create the shared memory segment, disable cache.
                $this->cache = null;
            }
        }
    }

    public function isCacheEnabled(): bool
    {
        return null !== $this->cache;
    }

    private function uuidStoreSharedMemorySegmentExists(): bool
    {
        return !is_bool($this->shmSegment) && shm_has_var($this->shmSegment, $this->shmVarKey);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function initUuidStore(): void
    {
        if ($this->disableCacheWorkaround) {
            // Get from APCu.
            $uuidStoreCacheEntry = $this->cache->getItem($this->issuer.'_uuidStore');
            if ($uuidStoreCacheEntry->isHit()) {
                $this->uuidStore = $uuidStoreCacheEntry->get();
            }
        } else {
            if (!$this->uuidStoreSharedMemorySegmentExists()) {
                // UuidStore does not exist, create UuidStore.
                shm_put_var($this->shmSegment, $this->shmVarKey, $this->uuidStore);
            }

            if (!$this->uuidStoreSharedMemorySegmentExists()) {
                // Failed to create UuidStore, disable cache.
                $this->cache = null;

                return;
            }

            // Get from shared memory segment.
            $store = shm_get_var($this->shmSegment, $this->shmVarKey);
            $this->uuidStore = is_array($store) ? $store : [];
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function saveUuidStore(): void
    {
        if ($this->disableCacheWorkaround) {
            // Save to APCu.
            $uuidStoreCacheEntry = $this->cache->getItem($this->issuer.'_uuidStore');
            $uuidStoreCacheEntry->set($this->uuidStore);
            $this->cache->save($uuidStoreCacheEntry);
        } elseif ($this->shmSegment)  {
            // Save to shared memory segment.
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
     *
     * @throws InvalidArgumentException
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
    public function getVersion(string $uuid): ?int
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
     *
     * @throws InvalidArgumentException
     */
    public function put(string $uuid, int $version, array $data): ?bool
    {
        if (null === $this->cache) {
            return null;
        }

        // Save current version to memory and return the save version.
        $saveVersion = $this->setVersion($uuid, $version);

        // Save data to APCu cache.
        $entry = $this->cache->getItem($this->issuer.'_'.$uuid.'_v'.$saveVersion);
        $entry->set($data);

        return $this->cache->save($entry);
    }

    /**
     * @param string $uuid
     *
     * @return array|null
     *
     * @throws InvalidArgumentException
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
     *
     * @throws InvalidArgumentException
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
