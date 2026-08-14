<?php

declare(strict_types=1);

namespace enricodias\EmailValidator;

use enricodias\EmailValidator\ServiceProviders\QuotaAwareServiceProviderInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Stores provider quota cooldowns separately from validation-result entries.
 */
final class ProviderCooldownCache
{
    /**
     * @var CacheItemPoolInterface|null
     */
    private $cache;

    public function __construct(?CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }

    public function isUnavailable(QuotaAwareServiceProviderInterface $provider): bool
    {
        if ($this->cache === null) return false;

        $item = $this->cache->getItem($this->getKey($provider));

        if (!$item->isHit()) return false;

        $value = $item->get();

        if (!\is_array($value) || !\array_key_exists('until', $value)) {
            $this->cache->deleteItem($item->getKey());

            return false;
        }

        try {
            $until = new \DateTimeImmutable($value['until']);
        } catch (\Exception $e) {
            $this->cache->deleteItem($item->getKey());

            return false;
        }

        if ($until > new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) return true;

        $this->cache->deleteItem($item->getKey());

        return false;
    }

    public function save(QuotaAwareServiceProviderInterface $provider, \DateTimeImmutable $until): void
    {
        if ($this->cache === null) return;

        $item = $this->cache->getItem($this->getKey($provider));
        $item->set(['until' => $until->format(\DateTimeInterface::ATOM)]);
        $item->expiresAt($until);

        $this->cache->save($item);
    }

    private function getKey(QuotaAwareServiceProviderInterface $provider): string
    {
        return 'email_validator_provider_cooldown_' . \hash('sha256', $provider->getQuotaCacheIdentity());
    }
}
