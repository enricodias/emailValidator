<?php

declare(strict_types=1);

namespace enricodias\EmailValidator;

use enricodias\EmailValidator\ServiceProviders\HighRiskInterface;
use enricodias\EmailValidator\ServiceProviders\ServiceProviderInterface;
use enricodias\EmailValidator\ServiceProviders\UserCheck;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * EmailValidator
 *
 * Validate and check for disposable/temporary/throw away emails
 *
 * @author Enrico Dias <enrico@enricodias.com>
 * @link   https://github.com/enricodias/emailValidator Github repository.
 */
class EmailValidator
{
    /**
     * Email to be validated.
     *
     * @var string
     */
    private $email = '';

    /**
     * List of registered service providers and their selection metadata.
     *
     * @var ServiceProviderEntry[]
     */
    protected $serviceProviders = [];

    /**
     * Service provider in use.
     *
     * @var ServiceProviderInterface
     */
    protected $provider;

    /**
     * PSR-18 HTTP client used to send API requests.
     *
     * @var ClientInterface
     */
    protected $httpClient;

    /**
     * PSR-17 request factory used to build API requests.
     *
     * @var RequestFactoryInterface
     */
    protected $requestFactory;

    /**
     * PSR-3 logger used to record validation activity and service provider issues.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Local list containing common disposable domains to lower the number of external API requests.
     * This list is intended to be short in order to not affect performance and avoid the need of constants updates.
     * Wildcards (*) are allowed.
     *
     * @var array
     */
    private $disposableDomains = [
        'mailinator.com',
        'yopmail.com',
        'guerrillamail.*',
        'sharklasers.com',
        'getnada.com',
    ];

    /**
     * Default result values.
     *
     * @var array
     */
    private $result = [
        'disposable'   => false,
        'alias'        => false,
        'did_you_mean' => '',
        'highRisk'     => false
    ];

    /**
     * Creates a new EmailValidator instance. The UserCheck provider is used by default.
     *
     * @see ServiceProviders\UserCheck UserCheck provider.
     *
     * @param ClientInterface|null $httpClient (optional) PSR-18 HTTP client used to send API requests.
     *                              Auto-discovered from the packages installed by the consumer (e.g. guzzlehttp/guzzle) if not provided.
     * @param RequestFactoryInterface|null $requestFactory (optional) PSR-17 request factory used to build API requests.
     *                                      Auto-discovered from the packages installed by the consumer (e.g. guzzlehttp/psr7) if not provided.
     * @param LoggerInterface|null $logger (optional) PSR-3 logger used to record validation activity and service provider issues.
     *                              A NullLogger is used if not provided.
     */
    public function __construct(?ClientInterface $httpClient = null, ?RequestFactoryInterface $requestFactory = null, ?LoggerInterface $logger = null)
    {
        $this->httpClient = $httpClient ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->logger = $logger ?? new NullLogger();

        $this->addProvider(new UserCheck(), 'UserCheck');

        $this->provider = $this->serviceProviders[0]->getProvider();
    }

    /**
     * Creates a new EmailValidator instance and returns it for chaining.
     *
     * @param ClientInterface|null $httpClient (optional) PSR-18 HTTP client used to send API requests.
     * @param RequestFactoryInterface|null $requestFactory (optional) PSR-17 request factory used to build API requests.
     * @param LoggerInterface|null $logger (optional) PSR-3 logger used to record validation activity and service provider issues.
     *
     * @return EmailValidator instance for chaining.
     */
    public static function create(?ClientInterface $httpClient = null, ?RequestFactoryInterface $requestFactory = null, ?LoggerInterface $logger = null): self
    {
        return new self($httpClient, $requestFactory, $logger);
    }

    /**
     * Add disposable domains to the local domain list.
     *
     * @see EmailValidator::$disposableDomains Local list of disposable domains.
     *
     * @param array $domains List of additional domains to checked locally.
     *
     * @return EmailValidator Return itself for chaining.
     */
    public function addDomains(array $domains = []): self
    {
        $this->disposableDomains = \array_merge($this->disposableDomains, $domains);

        return $this;
    }

    /**
     * Add a service provider.
     *
     * The provider must have a name to be able to be removed using the removeProvider() method. Registering a
     * provider with a name that is already in use replaces the existing entry.
     *
     * Providers are tried in ascending priority order (lower values first). Within the same priority, the provider
     * used is chosen randomly on every validate() call, weighted by $weight.
     *
     * @see EmailValidator::removeProvider()
     * @see EmailValidator::validate()
     *
     * @param ServiceProviderInterface $provider
     * @param string $name (optional) A name to reference this provider. Case-insensitive.
     * @param int $weight (optional) Relative chance of being picked among providers with the same priority. Must not be negative.
     * @param int $priority (optional) Providers with a lower priority value are tried first. Must not be negative.
     *
     * @throws \InvalidArgumentException If $weight or $priority is negative.
     *
     * @return EmailValidator Return itself for chaining.
     */
    public function addProvider(ServiceProviderInterface $provider, string $name = '', int $weight = 1, int $priority = 1): self
    {
        if ($weight < 0) throw new \InvalidArgumentException('Provider weight must not be negative.');

        if ($priority < 0) throw new \InvalidArgumentException('Provider priority must not be negative.');

        if ($provider instanceof LoggerAwareInterface) $provider->setLogger($this->logger);

        $registration = new ServiceProviderEntry($provider, $name, $weight, $priority);

        if ($name === '') {

            $this->serviceProviders[] = $registration;

            return $this;

        }

        foreach ($this->serviceProviders as $index => $existingRegistration) {

            if (! $existingRegistration->hasName($name)) continue;

            $this->serviceProviders[$index] = $registration;

            return $this;

        }

        $this->serviceProviders[] = $registration;

        return $this;
    }

    /**
     * Remove a service provider.
     *
     * @see EmailValidator::addProvider()
     *
     * @param string $name The service provider name. Case-insensitive.
     *
     * @return EmailValidator Return itself for chaining.
     */
    public function removeProvider(string $name): self
    {
        foreach ($this->serviceProviders as $index => $registration) {

            if (!$registration->hasName($name)) continue;

            unset($this->serviceProviders[$index]);

            return $this;

        }

        return $this;
    }

    /**
     * Remove all service providers.
     *
     * @see EmailValidator::$serviceProviders List of service providers.
     *
     * @return EmailValidator Return itself for chaining.
     */
    public function clearProviders(): self
    {
        $this->serviceProviders = [];
        $this->provider = null;

        return $this;
    }

    /**
     * Builds the provider trial order for a single validate() call.
     *
     * Providers are grouped by priority (ascending, lower first) and, within each priority group, ordered by a
     * weighted random draw so a higher $weight increases the chance of being tried earlier without guaranteeing it.
     *
     * @see EmailValidator::weightedShuffle()
     * @see EmailValidator::$serviceProviders List of registered service providers.
     *
     * @return ServiceProviderEntry[] Registrations in the order they should be tried.
     */
    private function getOrderedProviders(): array
    {
        $groupsByPriority = [];

        foreach ($this->serviceProviders as $registration) {

            $groupsByPriority[$registration->getPriority()][] = $registration;

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
     * @param ServiceProviderEntry[] $registrations Registrations sharing the same priority.
     *
     * @return ServiceProviderEntry[] The same registrations, randomly reordered.
     */
    private function weightedShuffle(array $registrations): array
    {
        $keyedRegistrations = [];

        foreach ($registrations as $registration) {

            $randomValue = \random_int(1, PHP_INT_MAX) / PHP_INT_MAX;

            $key = $registration->getWeight() === 0 ? 0.0 : $randomValue ** (1 / $registration->getWeight());

            $keyedRegistrations[] = ['key' => $key, 'registration' => $registration];

        }

        \usort($keyedRegistrations, static function (array $a, array $b): int {

            return $b['key'] <=> $a['key'];

        });

        return \array_column($keyedRegistrations, 'registration');
    }

    /**
     * Validates an email address.
     *
     * Providers are tried in ascending priority order, weighted-randomly within each priority, until one of them returns true.
     *
     * @see EmailValidator::getOrderedProviders()
     * @see EmailValidator::$serviceProviders List of service providers.
     *
     * @param string $email Email to be validated.
     *
     * @return EmailValidator Return itself for chaining.
     */
    public function validate(string $email): self
    {
        $this->resetResult();

        if (\filter_var($email, FILTER_VALIDATE_EMAIL) === false)  return $this;

        $this->email = \strtolower($email);
        $this->result['alias'] = $this->checkAlias($email);

        if ($this->checkDisposable() !== false) {

            $this->logValidationResult('local disposable domain list');

            return $this;

        }

        if (\count($this->serviceProviders) === 0) {

            $this->logValidationResult('none');

            return $this;

        }

        $providerName = 'none';

        foreach ($this->getOrderedProviders() as $registration) {

            $this->provider = $registration->getProvider();
            $providerName = $registration->getName() === '' ? \get_class($this->provider) : $registration->getName();

            if ($this->provider->validate($email, $this->httpClient, $this->requestFactory) !== false) break;

        }

        $this->result['disposable']   = $this->provider->isDisposable();
        $this->result['did_you_mean'] = $this->provider->didYouMean();

        if ($this->provider instanceof HighRiskInterface) $this->result['highRisk'] = $this->provider->isHighRisk();

        $this->logValidationResult($providerName);

        return $this;
    }

    /**
     * Records a validation result using the PSR-3 logger.
     *
     * @see EmailValidator::$logger PSR-3 logger instance.
     *
     * @param string $providerName Name of the service provider used, or a description when none was used.
     */
    private function logValidationResult(string $providerName): void
    {
        $this->logger->info('Email validated.', [
            'email'      => $this->email,
            'provider'   => $providerName,
            'valid'      => $this->isValid(),
            'disposable' => $this->result['disposable'],
        ]);
    }

    /**
     * Sets the email as disposable if its domain matches against any domain in the local domain list, including wildcards (*).
     *
     * @see EmailValidator::$disposableDomains Local domain list.
     *
     * @return boolean true if the email domain matched an entry in the local domain list.
     */
    private function checkDisposable(): bool
    {
        $emailDomain = \explode('@', $this->email, 2);
        $emailDomain = \array_pop($emailDomain);

        foreach ($this->disposableDomains as $domain) {

            if (\fnmatch($domain, $emailDomain) === true) {

                $this->setAsDisposable();

                return true;

            }

        }

        return false;
    }

    /**
     * Sets the email as disposable.
     */
    private function setAsDisposable(): void
    {
        $this->result['disposable'] = true;
    }

    /**
     * Resets the validation result to its default values.
     *
     * Called at the start of every validate() call so a previous result can never leak into the next one.
     */
    private function resetResult(): void
    {
        $this->email = '';

        $this->result = [
            'disposable'   => false,
            'alias'        => false,
            'did_you_mean' => '',
            'highRisk'     => false
        ];
    }

    /**
     * Checks if an email is an alias.
     *
     * Example: test+alias@domain.com
     *
     * @param string $email Email to be checked.
     *
     * @return bool true if the email is an alias
     */
    private function checkAlias(string $email): bool
    {
        return (bool) \stripos($email, '+');
    }

    /**
     * Checks if the email is valid. Disposable emails are also valid.
     *
     * @return boolean true if the email is valid.
     */
    public function isValid(): bool
    {
        if ($this->email === '') return false;

        if ($this->provider === null) return true;

        return $this->provider->isValid();
    }

    /**
     * Checks if the email is disposable.
     *
     * @return boolean true if the email is disposable.
     */
    public function isDisposable(): bool
    {
        return $this->result['disposable'];
    }

    /**
     * Checks if the email is an alias.
     *
     * @see EmailValidator::checkAlias()
     *
     * @return boolean true if the email is an alias.
     */
    public function isAlias(): bool
    {
        return $this->result['alias'];
    }

    /**
     * Tries to suggest a correction for common typos in the email.
     *
     * @return string A possible email suggestion or an empty string.
     */
    public function didYouMean(): string
    {
        return $this->result['did_you_mean'];
    }

    /**
     * Checks if the email risk score is considered high.
     *
     * Risk analysis is not supported by all providers.
     *
     * @return boolean true if the email is high risk.
     */
    public function isHighRisk(): bool
    {
        return $this->result['highRisk'];
    }

    /**
     * Returns the last service provider used.
     *
     * @return ServiceProviderInterface|null null if no provider has been used yet or clearProviders() was called.
     */
    public function getProvider(): ?ServiceProviderInterface
    {
        return $this->provider;
    }
}

