<?php

declare(strict_types=1);

namespace enricodias\EmailValidator;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * ValidationResultCache
 *
 * Wraps a PSR-6 cache pool to store and retrieve validation results, keyed by email, so the same
 * email is not validated twice. Caching is disabled whenever no cache pool is provided.
 *
 * @see EmailValidator::validate()
 */
class ValidationResultCache
{
    /**
     * @var CacheItemPoolInterface|null null if caching is disabled.
     */
    private $cache;

    public function __construct(?CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Retrieves the cache item for an email address.
     *
     * @return CacheItemInterface|null null if caching is disabled.
     */
    public function getItem(string $email): ?CacheItemInterface
    {
        if ($this->cache === null) return null;

        return $this->cache->getItem($this->getKey($email));
    }

    /**
     * Persists a validation result in the cache.
     *
     * @param CacheItemInterface $item Item retrieved from getItem() for the email being stored.
     * @param array $result Validation result to store.
     */
    public function save(CacheItemInterface $item, array $result): void
    {
        if ($this->cache === null) return;

        $item->set($result);

        $this->cache->save($item);
    }

    /**
     * Builds a PSR-6 compliant cache key for an email address.
     *
     * Cache keys cannot contain the reserved characters {}()/\@:, so the email is hashed instead of used directly.
     */
    private function getKey(string $email): string
    {
        return 'email_validator_' . \hash('sha256', $email);
    }
}
