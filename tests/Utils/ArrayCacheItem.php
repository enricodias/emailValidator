<?php

namespace enricodias\EmailValidator\Tests\Utils;

use Psr\Cache\CacheItemInterface;

/**
 * A minimal PSR-6 cache item used by ArrayCacheItemPool.
 */
final class ArrayCacheItem implements CacheItemInterface
{
    /**
     * @var string
     */
    private $key;

    /**
     * @var bool
     */
    private $isHit;

    /**
     * @var mixed
     */
    private $value;

    public function __construct(string $key, bool $isHit, $value)
    {
        $this->key = $key;
        $this->isHit = $isHit;
        $this->value = $value;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return mixed
     */
    public function get()
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->isHit;
    }

    public function set($value): self
    {
        $this->value = $value;
        $this->isHit = true;

        return $this;
    }

    public function expiresAt($expiration): self
    {
        return $this;
    }

    public function expiresAfter($time): self
    {
        return $this;
    }
}
