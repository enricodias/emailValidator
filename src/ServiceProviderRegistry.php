<?php

declare(strict_types=1);

namespace enricodias\EmailValidator;

use enricodias\EmailValidator\ServiceProviders\ServiceProviderInterface;

/**
 * ServiceProviderRegistry
 *
 * Holds the service providers registered with an EmailValidator instance and decides the order
 * in which they should be tried on each validate() call.
 *
 * @see EmailValidator::addProvider()
 * @see EmailValidator::validate()
 */
class ServiceProviderRegistry
{
    /**
     * @var ServiceProviderEntry[]
     */
    private $entries = [];

    /**
     * Registers a service provider.
     *
     * The provider must have a name to be able to be removed using remove(). Registering a provider
     * with a name that is already in use replaces the existing entry.
     *
     * @see ServiceProviderRegistry::remove()
     *
     * @param ServiceProviderInterface $provider
     * @param string $name (optional) A name to reference this provider. Case-insensitive.
     * @param int $weight (optional) Relative chance of being picked among providers with the same priority. Must not be negative.
     * @param int $priority (optional) Providers with a lower priority value are tried first. Must not be negative.
     *
     * @throws \InvalidArgumentException If $weight or $priority is negative.
     */
    public function add(ServiceProviderInterface $provider, string $name = '', int $weight = 1, int $priority = 1): void
    {
        $entry = new ServiceProviderEntry($provider, $name, $weight, $priority);

        if ($name === '') {

            $this->entries[] = $entry;

            return;

        }

        foreach ($this->entries as $index => $existingEntry) {

            if (! $existingEntry->hasName($name)) continue;

            $this->entries[$index] = $entry;

            return;

        }

        $this->entries[] = $entry;
    }

    /**
     * Removes a service provider.
     *
     * @param string $name The service provider name. Case-insensitive.
     */
    public function remove(string $name): void
    {
        foreach ($this->entries as $index => $entry) {

            if (! $entry->hasName($name)) continue;

            unset($this->entries[$index]);

            return;

        }
    }

    /**
     * Removes all registered service providers.
     */
    public function clear(): void
    {
        $this->entries = [];
    }

    /**
     * Number of registered service providers.
     */
    public function count(): int
    {
        return \count($this->entries);
    }

    /**
     * Builds the provider trial order for a single validate() call.
     *
     * Providers are grouped by priority (ascending, lower first) and, within each priority group, ordered by a
     * weighted random draw so a higher $weight increases the chance of being tried earlier without guaranteeing it.
     *
     * @see ServiceProviderRegistry::weightedShuffle()
     *
     * @return ServiceProviderEntry[] Registrations in the order they should be tried.
     */
    public function getOrderedProviders(): array
    {
        $groupsByPriority = [];

        foreach ($this->entries as $entry) {

            $groupsByPriority[$entry->getPriority()][] = $entry;

        }

        \ksort($groupsByPriority);

        $ordered = [];

        foreach ($groupsByPriority as $group) {

            $ordered = \array_merge($ordered, $this->weightedShuffle($group));

        }

        return $ordered;
    }

    /**
     * Randomly orders provider registrations using weighted sampling without replacement (Efraimidis-Spirakis algorithm).
     *
     * Each registration gets a random key of random()^(1/weight) and registrations are sorted by that key,
     * descending. A weight of 0 always sorts last within the group.
     *
     * @param ServiceProviderEntry[] $entries Registrations sharing the same priority.
     *
     * @return ServiceProviderEntry[] The same registrations, randomly reordered.
     */
    private function weightedShuffle(array $entries): array
    {
        $keyedEntries = [];

        foreach ($entries as $entry) {

            $randomValue = \random_int(1, PHP_INT_MAX) / PHP_INT_MAX;

            $key = $entry->getWeight() === 0 ? 0.0 : $randomValue ** (1 / $entry->getWeight());

            $keyedEntries[] = ['key' => $key, 'entry' => $entry];

        }

        \usort($keyedEntries, static function (array $a, array $b): int {

            return $b['key'] <=> $a['key'];

        });

        return \array_column($keyedEntries, 'entry');
    }
}
