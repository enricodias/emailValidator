<?php

declare(strict_types=1);

namespace enricodias\EmailValidator\ServiceProviders;

/**
 * HighRiskInterface
 *
 * Interface used by service providers that support high risk email detection.
 */
interface HighRiskInterface
{
    /**
     * Checks if the email risk score is considered high.
     *
     * @return boolean true if the email is high risk.
     */
    public function isHighRisk(): bool;
}
