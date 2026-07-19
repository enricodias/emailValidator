<?php

declare(strict_types=1);

namespace enricodias\EmailValidator;

use enricodias\EmailValidator\ServiceProviders\HighRiskInterface;
use enricodias\EmailValidator\ServiceProviders\ServiceProviderInterface;
use enricodias\EmailValidator\ServiceProviders\UserCheck;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * EmailValidator
 *
 * Validate and check for disposable/temporary/throw away emails
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
     * Registered service providers and the logic that decides which one to try, and in what order.
     *
     * @var ServiceProviderRegistry
     */
    private $registry;

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
     * Stores validation results, keyed by email, so the same email is not validated twice.
     *
     * @var ValidationResultCache
     */
    private $resultCache;

    /**
     * Local list containing common disposable domains to lower the number of external API requests.
     * Wildcards (*) are allowed.
     *
     * @var array
     */
    private $disposableDomains = [];

    /**
     * Default result values.
     *
     * @var array
     */
    private $result = [
        'disposable'   => false,
        'alias'        => false,
        'did_you_mean' => '',
        'highRisk'     => false,
        'valid'        => false
    ];

    public function __construct(?ClientInterface $httpClient = null, ?RequestFactoryInterface $requestFactory = null, ?CacheItemPoolInterface $cache = null, ?LoggerInterface $logger = null)
    {
        $this->httpClient = $httpClient ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->resultCache = new ValidationResultCache($cache);
        $this->logger = $logger ?? new NullLogger();
        $this->registry = new ServiceProviderRegistry();

        $defaultProvider = new UserCheck();

        $this->addProvider($defaultProvider, 'UserCheck');

        $this->provider = $defaultProvider;
    }

    /**
     * Creates a new EmailValidator instance and returns it for chaining.
     *
     * @param ClientInterface|null $httpClient (optional) PSR-18 HTTP client used to send API requests.
     * @param RequestFactoryInterface|null $requestFactory (optional) PSR-17 request factory used to build API requests.
     * @param LoggerInterface|null $logger (optional) PSR-3 logger used to record validation activity and service provider issues.
     */
    public static function create(?ClientInterface $httpClient = null, ?RequestFactoryInterface $requestFactory = null, ?CacheItemPoolInterface $cache = null, ?LoggerInterface $logger = null): self
    {
        return new self($httpClient, $requestFactory, $cache, $logger);
    }

    /**
     * Add disposable domains to the local domain list.
     *
     * @see EmailValidator::$disposableDomains Local list of disposable domains.
     *
     * @param array $domains List of additional domains to checked locally.
     */
    public function addDisposableDomains(array $domains = []): self
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
     */
    public function addProvider(ServiceProviderInterface $provider, string $name = '', int $weight = 1, int $priority = 1): self
    {
        if ($provider instanceof LoggerAwareInterface) $provider->setLogger($this->logger);

        $this->registry->add($provider, $name, $weight, $priority);

        return $this;
    }

    /**
     * Remove a service provider.
     *
     * @see EmailValidator::addProvider()
     *
     * @param string $name The service provider name. Case-insensitive.
     */
    public function removeProvider(string $name): self
    {
        $this->registry->remove($name);

        return $this;
    }

    /**
     * Remove all service providers.
     *
     * @see EmailValidator::$registry Registered service providers.
     */
    public function clearProviders(): self
    {
        $this->registry->clear();
        $this->provider = null;

        return $this;
    }

    /**
     * Validates an email address.
     *
     * Providers are tried in ascending priority order, weighted-randomly within each priority, until one of them returns true.
     *
     * @see ServiceProviderRegistry::getOrderedProviders()
     * @see EmailValidator::$registry Registered service providers.
     *
     * @throws \LogicException If the email requires a service provider to be validated but none is registered.
     */
    public function validate(string $email): self
    {
        $this->resetResult();

        if (\filter_var($email, FILTER_VALIDATE_EMAIL) === false)  return $this;

        $this->email = \strtolower($email);

        $cacheItem = $this->resultCache->getItem($this->email);

        if ($cacheItem !== null && $cacheItem->isHit()) {

            $this->result = $cacheItem->get();

            $this->logValidationResult('cache');

            return $this;

        }

        $this->result['alias'] = $this->checkAlias($email);

        if ($this->checkDisposable() !== false) {

            $this->result['valid'] = true;

            $this->logValidationResult('local disposable domain list');

            return $this;

        }

        if ($this->registry->count() === 0) throw new \LogicException('At least one service provider must be registered before calling validate().');

        $providerName = 'none';

        foreach ($this->registry->getOrderedProviders() as $registration) {

            $this->provider = $registration->getProvider();
            $providerName = $registration->getName() === '' ? \get_class($this->provider) : $registration->getName();

            if ($this->provider->validate($email, $this->httpClient, $this->requestFactory) !== false) break;

        }

        $this->result['disposable'] = $this->provider->isDisposable();
        $this->result['did_you_mean'] = $this->provider->didYouMean();
        $this->result['valid'] = $this->provider->isValid();

        if ($this->provider instanceof HighRiskInterface) $this->result['highRisk'] = $this->provider->isHighRisk();

        if ($cacheItem !== null) $this->resultCache->save($cacheItem, $this->result);

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

                $this->result['disposable'] = true;

                return true;

            }

        }

        return false;
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
            'highRisk'     => false,
            'valid'        => false,
        ];
    }

    /**
     * Checks if an email is an alias.
     *
     * Example: test+alias@domain.com
     */
    private function checkAlias(string $email): bool
    {
        return (bool) \stripos($email, '+');
    }

    /**
     * Checks if the email is valid. Disposable emails are also valid.
     */
    public function isValid(): bool
    {
        return $this->result['valid'];
    }

    /**
     * Checks if the email is disposable.
     */
    public function isDisposable(): bool
    {
        return $this->result['disposable'];
    }

    /**
     * Checks if the email is an alias.
     *
     * @see EmailValidator::checkAlias()
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

