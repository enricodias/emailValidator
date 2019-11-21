<?php

namespace enricodias;

use enricodias\EmailValidatorAdapter\AdapterInterface;
use GuzzleHttp\Client;

/**
 * EmailValidator
 * 
 * Validate and check for disposable/temporary/throw away emails using validator.pizza
 * 
 * @see    https://www.validator.pizza/ validator.pizza API.
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
    private $_email = '';

    /**
     * Adapter used to test the email.
     *
     * @var AdapterInterface
     */
    protected $_adapter;

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
    );

    /**
     * Creates a new EmailValidator instance and validate an email address.
     *
     * @see EmailValidator::$_disposableDomains Local domain list.
     * 
     * @param string $email Email to be validated.
     * @param array $additionalDomains List of additional domains to checked locally.
     */
    public function __construct($email, array $additionalDomains = [], AdapterInterface $adapter = null)
    {
        if ($adapter === null) $this->_adapter = new EmailValidatorAdapter\ValidatorPizzaAdapter();

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) return;

        $this->_email = strtolower($email);

        if ($this->checkDisposable($additionalDomains) === false) $this->_adapter->validate($email, $this->getGuzzleClient());
    }

    /**
     * Sets the email as disposable if its domain matches against any domain in the local domain list, including wildcards (*).
     *
     * @see EmailValidator::$_disposableDomains Local domain list.
     * 
     * @param array $additionalDomains List of additional domains to checked locally.
     * @return void
     */
    private function checkDisposable(array $additionalDomains)
    {
        $emailDomain = explode('@', $this->_email, 2);
        $emailDomain = array_pop($emailDomain);

        $disposableDomains = array_merge($this->_disposableDomains, $additionalDomains);

        foreach ($disposableDomains as $domain) {

            if (fnmatch($domain, $emailDomain) === true) return $this->setAsDisposable();
            
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
        
        return true;
    }

    /**
     * Checks if the email is valid. Disposable emails are also valid.
     *
     * @return boolean true if the email is valid.
     */
    public function isValid()
    {
        if ($this->_email === '') return false;

        return $this->_adapter->isValid();
    }

    /**
     * Checks if the email is disposable.
     *
     * @return boolean true if the email is disposable.
     */
    public function isDisposable()
    {
        if ($this->_result['disposable'] === false) return $this->_adapter->isDisposable();

        return true;
    }

    /**
     * Checks if the email is an alias.
     * Example: test+alias@domain.com
     *
     * @return boolean true if the email is an alias.
     */
    public function isAlias()
    {
        if ($this->_result['alias'] === false) return $this->_adapter->isAlias();

        return true;
    }

    /**
     * Tries to suggest a correction for common typos in the email.
     *
     * @return string A possible email suggestion or an empty string.
     */
    public function didYouMean()
    {
        return $this->_adapter->didYouMean();
    }

    /**
     * Returns the number allowed requests left in validator.pizza's API in the current hour.
     *
     * @return int Number requests left.
     */
    public function getRequestsLeft()
    {
        return $this->_adapter->getRequestsLeft();
    }

    /**
     * Creates GuzzleHttp\Client to be used in API requests.
     * This method is needed to test API failures in unit tests.
     *
     * @return object GuzzleHttp\Client instance.
     */
    public function getGuzzleClient()
    {
        return new Client();
    }
}