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
     * List of service providers to be used.
     *
     * @var ServiceProviderInterface[]
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

        $this->provider = current($this->serviceProviders);
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
     * @param array $additionalDomains List of additional domains to checked locally.
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
     * The provider must have a name to be able to be removed using the removeProvider() method.
     *
     * @see EmailValidator::removeProvider()
     *
     * @param ServiceProviderInterface $provider
     * @param string $name (optional) A name to reference this provider. Case-insensitive.
     *
     * @return EmailValidator Return itself for chaining.
     */
    public function addProvider(ServiceProviderInterface $provider, string $name = ''): self
    {
        if ($provider instanceof LoggerAwareInterface) $provider->setLogger($this->logger);

        if ($name === '') {

            $this->serviceProviders[] = $provider;

            return $this;

        }

        $this->serviceProviders[\strtolower($name)] = $provider;

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
        $name = \strtolower($name);

        if (\array_key_exists($name, $this->serviceProviders)) unset($this->serviceProviders[$name]);

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
     * Shuffle the service provider list.
     *
     * Uses a key-preserving Fisher-Yates shuffle with random_int()
     *
     * @see EmailValidator::$serviceProviders List of service providers.
     *
     * @return EmailValidator Return itself for chaining.
     */
    public function shuffleProviders(): self
    {
        $keys = \array_keys($this->serviceProviders);

        for ($i = \count($keys) - 1; $i > 0; $i--) {

            $j = \random_int(0, $i);

            [$keys[$i], $keys[$j]] = [$keys[$j], $keys[$i]];

        }

        $shuffled = [];

        foreach ($keys as $key) {

            $shuffled[$key] = $this->serviceProviders[$key];

        }

        $this->serviceProviders = $shuffled;

        return $this;
    }

    /**
     * Validates an email address.
     *
     * The providers from EmailValidator::$serviceProviders will be used in sequence until one of them returns true.
     *
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

        foreach ($this->serviceProviders as $name => $provider) {

            $this->provider = $provider;
            $providerName   = \is_int($name) ? \get_class($provider) : $name;

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

