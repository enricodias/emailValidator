<?php

namespace enricodias\EmailValidator;

use enricodias\EmailValidator\ServiceProviders\ServiceProviderInterface;
use enricodias\EmailValidator\ServiceProviders\ValidatorPizza;
use GuzzleHttp\Client;

/**
 * EmailValidator
 * 
 * Validate and check for disposable/temporary/throw away emails using validator.pizza
 * 
 * @see https://www.validator.pizza/ validator.pizza API.
 * 
 * @author Enrico Dias <enrico@enricodias.com>
 */
class EmailValidator
{
    /**
     * Email to be validated.
     *
     * @var string
     */
    private $_email = '';

    /**
     * List of service providers to be used.
     *
     * @var ServiceProviderInterface
     */
    protected $_serviceProviders = array();

    /**
     * Service provider in use.
     *
     * @var ServiceProviderInterface
     */
    protected $_provider;

    /**
     * Local list containing common disposable domains to lower the number of API requests to validator.pizza's API.
     * This list is intended to be short in order to not affect performance and avoid the need of constants updates.
     * Wildcards (*) are allowed.
     *
     * @var array
     */
    private $_disposableDomains = array(
        'mailinator.com',
        'yopmail.com',
        'guerrillamail.*',
        'sharklasers.com',
        'getnada.com',
    );

    /**
     * Default result values.
     *
     * @var array
     */
    private $_result = array(
        'disposable'   => false,
        'alias'        => false,
        'did_you_mean' => '',
        'highRisk'     => false
    );

    /**
     * Creates a new EmailValidator instance. The validator.pizza provider is used by default.
     * 
     * @see ServiceProviders\ValidatorPizza validator.pizza provider.
     */
    public function __construct()
    {
        $this->addProvider(new ValidatorPizza(), 'validator.pizza');

        $this->_provider = current($this->_serviceProviders);
    }

    /**
     * Creates a new EmailValidator instance and returns it for chaining.
     * 
     * @return EmailValidator instance for chaining.
     */
    public static function create()
    {
        return new self();
    }

    /**
     * Add disposable domains to the local domain list.
     * 
     * @see EmailValidator::$_disposableDomains Local list of disposable domains.
     * 
     * @param array $additionalDomains List of additional domains to checked locally.
     * @return EmailValidator Return itself for chaining.
     */
    public function addDomains(array $domains = [])
    {
        $this->_disposableDomains = array_merge($this->_disposableDomains, $domains);

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
     * @return EmailValidator Return itself for chaining.
     */
    public function addProvider(ServiceProviderInterface $provider, $name = '')
    {
        $this->_serviceProviders[strtolower($name)] = $provider;

        return $this;
    }

    /**
     * Remove a service provider.
     *
     * @see EmailValidator::addProvider()
     * 
     * @param string $name The service provider name. Case-insensitive.
     * @return EmailValidator Return itself for chaining.
     */
    public function removeProvider($name)
    {
        $name = strtolower($name);

        if (array_key_exists($name, $this->_serviceProviders)) unset($this->_serviceProviders[$name]);

        return $this;
    }

    /**
     * Remove all service providers.
     *
     * @see EmailValidator::$_serviceProviders List of service providers.
     * 
     * @return EmailValidator Return itself for chaining.
     */
    public function clearProviders()
    {
        $this->_serviceProviders = array();
        $this->_provider = null;

        return $this;
    }

    /**
     * Validates an email address.
     * 
     * The providers from EmailValidator::$_serviceProviders will be used in sequence until one of them returns true.
     *
     * @see EmailValidator::$_serviceProviders List of service providers.
     * 
     * @param string $email Email to be validated.
     * @return EmailValidator Return itself for chaining.
     */
    public function validate($email)
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) return $this;

        $this->_email = strtolower($email);

        $this->_result['alias'] = $this->checkAlias($email);

        if ($this->checkDisposable() !== false) return $this;

        if (count($this->_serviceProviders) === 0) return $this;

        foreach ($this->_serviceProviders as $provider) {

            $this->_provider = $provider;

            if ($this->_provider->validate($email, $this->getGuzzleClient()) !== false) break;

        }
        
        $this->_result['disposable']   = $this->_provider->isDisposable();
        $this->_result['did_you_mean'] = $this->_provider->didYouMean();

        if (method_exists($this->_provider, 'isHighRisk')) $this->_result['highRisk'] = $this->_provider->isHighRisk();

        return $this;
    }

    /**
     * Sets the email as disposable if its domain matches against any domain in the local domain list, including wildcards (*).
     *
     * @see EmailValidator::$_disposableDomains Local domain list.
     * 
     * @return void
     */
    private function checkDisposable()
    {
        $emailDomain = explode('@', $this->_email, 2);
        $emailDomain = array_pop($emailDomain);

        foreach ($this->_disposableDomains as $domain) {

            if (fnmatch($domain, $emailDomain) === true) {

                $this->setAsDisposable();

                return true;

            }
            
        }

        return false;
    }

    /**
     * Sets the email as disposable.
     *
     * @return void
     */
    private function setAsDisposable()
    {
        $this->_result['disposable'] = true;
    }

    /**
     * Checks if an email is an alias.
     * 
     * Example: test+alias@domain.com
     *
     * @param string $email Email to be checked.
     * @return bool true if the email is an alias
     */
    private function checkAlias($email)
    {
        return (bool) stripos($email, '+');
    }

    /**
     * Checks if the email is valid. Disposable emails are also valid.
     *
     * @return boolean true if the email is valid.
     */
    public function isValid()
    {
        if ($this->_email === '') return false;

        if ($this->_provider === null) return true;

        return $this->_provider->isValid();
    }

    /**
     * Checks if the email is disposable.
     *
     * @return boolean true if the email is disposable.
     */
    public function isDisposable()
    {
        return $this->_result['disposable'];
    }

    /**
     * Checks if the email is an alias.
     * 
     * @see EmailValidator::checkAlias()
     *
     * @return boolean true if the email is an alias.
     */
    public function isAlias()
    {
        return $this->_result['alias'];
    }

    /**
     * Tries to suggest a correction for common typos in the email.
     *
     * @return string A possible email suggestion or an empty string.
     */
    public function didYouMean()
    {
        return $this->_result['did_you_mean'];
    }
    
    /**
     * Checks if the email risk score is considered high.
     * 
     * Risk analysis is not supported by all providers.
     *
     * @return boolean true if the email is high risk.
     */
    public function isHighRisk()
    {
        return $this->_result['highRisk'];
    }

    /**
     * Creates GuzzleHttp\Client to be used in API requests.
     * This method is needed to test API calls in unit tests.
     *
     * @return object GuzzleHttp\Client instance.
     */
    public function getGuzzleClient()
    {
        return new Client();
    }

    /**
     * Returns the last service provider used.
     *
     * @return ServiceProviderInterface
     */
    public function getProvider()
    {
        return $this->_provider;
    }
}