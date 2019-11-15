<?php

namespace enricodias;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

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
     * Default values returned by validator.pizza's API.
     *
     * @var array
     */
    private $_result = array(
        'status'             => 0,
        'domain'             => '',
        'mx'                 => false,
        'disposable'         => false,
        'alias'              => false,
        'did_you_mean'       => false,
        'remaining_requests' => 120,
    );

    /**
     * Creates a new EmailValidator instance and validate an email address.
     *
     * @see EmailValidator::$_disposableDomains Local domain list.
     * 
     * @param string $email Email to be validated.
     * @param array $additionalDomains List of additional domains to checked locally.
     */
    public function __construct($email, array $additionalDomains = [])
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) return;

        $this->_email = strtolower($email);
        
        if ($this->checkDisposable($additionalDomains) === false) $this->fetchValidatorPizza();
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

            if (fnmatch($domain, $emailDomain) === true) return $this->setAsDisposable($domain);
            
        }

        return false;
    }

    /**
     * Sets the email as disposable.
     *
     * @param string $domain The email's domain name.
     * @return void
     */
    private function setAsDisposable($domain)
    {
        $this->_result['domain']     = $domain;
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

        if ($this->_result['status'] !== 0) { // local status check

            // we should assume the email to be valid if we get any status other than 400 from the API
            if ($this->_result['status'] === 400) return false;

        }

        return true;
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
     * Example: test+alias@domain.com
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
        if ($this->_result['did_you_mean'] == false) return '';

        $email = str_ireplace($this->_result['domain'], $this->_result['did_you_mean'], $this->_email);

        return $email;
    }

    /**
     * Returns the number allowed requests left in validator.pizza's API in the current hour.
     *
     * @return int Number requests left.
     */
    public function getRequestsLeft()
    {
        return $this->_result['remaining_requests'];
    }
    
    /**
     * Makes the request to validator.pizza's API.
     *
     * @return void
     */
    private function fetchValidatorPizza()
    {
        $client = new Client(['base_uri' => 'https://www.validator.pizza/email/']);

        $request = new Request('GET', $this->_email, ['Accept' => 'application/json']);

        try {

            $response = $client->send($request);

        } catch (\Exception $e) {
            
            return;
            
        }

        $response = json_decode($response->getBody(), true);
        
        if (json_last_error() != JSON_ERROR_NONE) return;

        $this->validateResponse($response);
    }

    /**
     * Processes a response from validator.pizza's API.
     *
     * @param string $response Response from validator.pizza's API.
     * @return void
     */
    private function validateResponse($response)
    {
        if (!$this->checkValidStatus($response['status'])) return;
        
        if ($response['status'] === 200) {
            
            $this->_result = $response;

            return;

        }

        $this->_result['status'] = $response['status'];
    }

    /**
     * Validates the status returned by the validator.pizza's API to verify whether or not we can trust the response.
     * The only valid values are 200, 400 and 429.
     *
     * @param int $status Status code.
     * @return boolean true if the status code is valid.
     */
    private function checkValidStatus($status)
    {
        if ($status !== 200 && $status !== 400 && $status !== 429) return false;

        return true;
    }
}