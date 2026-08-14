<?php

namespace enricodias\EmailValidator\Tests;

use enricodias\EmailValidator\ProviderCooldownCache;
use enricodias\EmailValidator\ServiceProviders\QuickEmailVerification;
use enricodias\EmailValidator\ServiceProviders\QuotaAwareServiceProviderInterface;
use enricodias\EmailValidator\Tests\Utils\ArrayCacheItemPool;
use PHPUnit\Framework\TestCase;

final class ProviderCooldownCacheTest extends TestCase
{
    public function testIsUnavailableReturnsFalseWhenCachingIsDisabled(): void
    {
        $cooldownCache = new ProviderCooldownCache(null);

        $this->assertFalse($cooldownCache->isUnavailable(new QuickEmailVerification('some-key')));
    }

    public function testIsUnavailableReturnsFalseWhenNothingWasSaved(): void
    {
        $cooldownCache = new ProviderCooldownCache(new ArrayCacheItemPool());

        $this->assertFalse($cooldownCache->isUnavailable(new QuickEmailVerification('some-key')));
    }

    public function testIsUnavailableReturnsTrueForAnActiveCooldown(): void
    {
        $cache = new ArrayCacheItemPool();
        $cooldownCache = new ProviderCooldownCache($cache);
        $provider = new QuickEmailVerification('some-key');

        $cooldownCache->save($provider, new \DateTimeImmutable('+1 hour', new \DateTimeZone('UTC')));

        $this->assertTrue($cooldownCache->isUnavailable($provider));
        $this->assertSame(1, $cache->count());
    }

    public function testIsUnavailableDeletesAndReturnsFalseForAnExpiredCooldown(): void
    {
        $cache = new ArrayCacheItemPool();
        $cooldownCache = new ProviderCooldownCache($cache);
        $provider = new QuickEmailVerification('some-key');

        $cooldownCache->save($provider, new \DateTimeImmutable('-1 hour', new \DateTimeZone('UTC')));

        $this->assertFalse($cooldownCache->isUnavailable($provider));
        $this->assertSame(0, $cache->count());
    }

    public function testIsUnavailableDeletesAndReturnsFalseForAMalformedValue(): void
    {
        $cache = new ArrayCacheItemPool();
        $cooldownCache = new ProviderCooldownCache($cache);
        $provider = new QuickEmailVerification('some-key');

        $this->storeRawValue($cache, $provider, 'not-an-array');

        $this->assertFalse($cooldownCache->isUnavailable($provider));
        $this->assertSame(0, $cache->count());
    }

    public function testIsUnavailableDeletesAndReturnsFalseWhenTheUntilKeyIsMissing(): void
    {
        $cache = new ArrayCacheItemPool();
        $cooldownCache = new ProviderCooldownCache($cache);
        $provider = new QuickEmailVerification('some-key');

        $this->storeRawValue($cache, $provider, ['reason' => 'quota reached']);

        $this->assertFalse($cooldownCache->isUnavailable($provider));
        $this->assertSame(0, $cache->count());
    }

    public function testIsUnavailableDeletesAndReturnsFalseForAnInvalidUntilDate(): void
    {
        $cache = new ArrayCacheItemPool();
        $cooldownCache = new ProviderCooldownCache($cache);
        $provider = new QuickEmailVerification('some-key');

        $this->storeRawValue($cache, $provider, ['until' => 'not-a-date']);

        $this->assertFalse($cooldownCache->isUnavailable($provider));
        $this->assertSame(0, $cache->count());
    }

    /**
     * Stores a raw value under the same cache key ProviderCooldownCache would use for the given
     * provider, bypassing save() so malformed values can be simulated.
     *
     * @param mixed $value
     */
    private function storeRawValue(ArrayCacheItemPool $cache, QuotaAwareServiceProviderInterface $provider, $value): void
    {
        $item = $cache->getItem($this->getKey($provider));
        $item->set($value);

        $cache->save($item);
    }

    private function getKey(QuotaAwareServiceProviderInterface $provider): string
    {
        return 'email_validator_provider_cooldown_' . \hash('sha256', $provider->getQuotaCacheIdentity());
    }
}
