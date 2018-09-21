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
     * @var null|ApcuAdapter
     */
    private $cache = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->issuer = isset($config['site_name']) ? $config['site_name'] : 'revisionTen';

        if (extension_loaded('apcu') && ini_get('apc.enabled')) {
            $this->cache = new ApcuAdapter();
        }
    }

    private function setVersion(string $uuid, int $version): ?int
    {
        // Todo: Save the version for this uuid in memory and return the version.

        return 1;
    }

    private function getVersion(string $uuid): ?int
    {
        // Todo: Get the version for this uuid from memory.

        return 1;
    }

    private function deleteVersion(string $uuid): ?int
    {
        // Todo: Delete the version for this uuid in memory and return the deleted version.

        return 1;
    }

    public function put(string $uuid, int $version, array $data): ?bool
    {
        if (null === $this->cache) {
            return null;
        }

        // Save current version to memory and return the save version.
        $version = $this->setVersion($uuid, $version);

        // Save data to apc cache.
        $entry = $this->cache->getItem($this->issuer.$uuid.'v'.$version);
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
            $entry = $this->cache->getItem($this->issuer.$uuid.'v'.$version);

            if ($entry->isHit()) {
                $data = $entry->get();
            }
        }

        return $data;
    }

    public function delete(string $uuid, int $version): ?bool
    {
        // Delete the version from memory and return the deleted version.
        $version = $this->deleteVersion($uuid);

        return $this->cache->deleteItem($this->issuer.$uuid.'v'.$version);
    }
}
