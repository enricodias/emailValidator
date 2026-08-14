<?php

declare(strict_types=1);

namespace enricodias\EmailValidator\ServiceProviders;

/**
 * A provider that can report when its account quota is unavailable.
 */
interface QuotaAwareServiceProviderInterface extends ServiceProviderInterface
{
    /**
     * Returns an identifier for quota state without exposing credentials.
     */
    public function getQuotaCacheIdentity(): string;

    /**
     * Returns the time at which the detected quota exhaustion is expected to end.
     */
    public function getQuotaCooldownUntil(): ?\DateTimeImmutable;
}
