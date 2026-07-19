<?php

declare(strict_types=1);

namespace enricodias\EmailValidator;

use enricodias\EmailValidator\ServiceProviders\ServiceProviderInterface;

/**
 * Bundles a registered service provider with the metadata used by EmailValidator to decide when it's tried.
 *
 * @see EmailValidator::addProvider()
 */
final class ServiceProviderEntry
{
    /**
     * @var ServiceProviderInterface
     */
    private $provider;

    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $weight;

    /**
     * @var int
     */
    private $priority;

    /**
     * Creates a new service provider registration.
     *
     * @param ServiceProviderInterface $provider
     * @param string $name Case-insensitive name used to reference this provider. Empty when unnamed.
     * @param int $weight Relative chance of being picked among providers with the same priority.
     * @param int $priority Providers with a lower priority value are tried first.
     *
     * @throws \InvalidArgumentException If $weight or $priority is negative.
     */
    public function __construct(ServiceProviderInterface $provider, string $name, int $weight, int $priority)
    {
        if ($weight < 0) throw new \InvalidArgumentException('Provider weight must not be negative.');

        if ($priority < 0) throw new \InvalidArgumentException('Provider priority must not be negative.');

        $this->provider = $provider;
        $this->name = $name;
        $this->weight  = $weight;
        $this->priority = $priority;
    }

    public function getProvider(): ServiceProviderInterface
    {
        return $this->provider;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Checks whether this registration was named, case-insensitively, as $name.
     *
     * Unnamed registrations (empty name) never match.
     */
    public function hasName(string $name): bool
    {
        if ($this->name === '') return false;

        return \strtolower($this->name) === \strtolower($name);
    }
}
