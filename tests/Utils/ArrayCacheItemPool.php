<?php

namespace enricodias\EmailValidator\Tests\Utils;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * A simple in-memory PSR-6 cache pool used to test caching behaviour without depending on a real implementation.
 */
final class ArrayCacheItemPool implements CacheItemPoolInterface
{
    /**
     * Stored cache values, keyed by cache key.
     *
     * @var array
     */
    private $values = [];

    /**
     * Number of times getItem() was called.
     *
     * @var int
     */
    private $hits = 0;

    public function getItem($key): CacheItemInterface
    {
        $this->hits++;

        $isHit = \array_key_exists($key, $this->values);

        return new ArrayCacheItem($key, $isHit, $isHit ? $this->values[$key] : null);
    }

    public function getItems(array $keys = []): iterable
    {
        $items = [];

        foreach ($keys as $key) {

            $items[$key] = $this->getItem($key);

        }

        return $items;
    }

    public function hasItem($key): bool
    {
        return \array_key_exists($key, $this->values);
    }

    public function clear(): bool
    {
        $this->values = [];

        return true;
    }

    public function deleteItem($key): bool
    {
        unset($this->values[$key]);

        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {

            $this->deleteItem($key);

        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        $this->values[$item->getKey()] = $item->get();

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        return true;
    }

    /**
     * Returns how many times getItem() was called, useful to assert whether the cache was consulted.
     */
    public function getHitCount(): int
    {
        return $this->hits;
    }

    /**
     * Checks whether a value was stored for the given raw key.
     */
    public function contains(string $key): bool
    {
        return \array_key_exists($key, $this->values);
    }
}
