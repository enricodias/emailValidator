<?php

namespace enricodias;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class EmailValidator
{
    private $_email = '';

    private $_disposableDomains = array(
        'mailinator.com',
        'yopmail.com',
        'guerrillamail.*',
        'sharklasers.com',
        'getnada.com',
    );

    private $_result = array(
        'status'             => 0,
        'domain'             => '',
        'mx'                 => false,
        'disposable'         => false,
        'alias'              => false,
        'did_you_mean'       => false,
        'remaining_requests' => 120,
    );

    public function __construct($email, array $additionalDomains = [])
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) return;

        $this->_email = strtolower($email);
        
        if ($this->checkDisposable($additionalDomains) === false) $this->fetchValidatorPizza();
    }

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

    private function setAsDisposable($domain)
    {
        $this->_result['domain']     = $domain;
        $this->_result['disposable'] = true;
        
        return true;
    }

    public function isValid()
    {
        if ($this->_email === '') return false;

        if ($this->_result['status'] !== 0) {

            // the email could be valid if we get any status other than 400
            if ($this->_result['status'] === 400 || $this->_result['mx'] === false) return false;

        }

        return true;
    }

    public function isDisposable()
    {
        return $this->_result['disposable'];
    }

    public function isAlias()
    {
        return $this->_result['alias'];
    }

    public function didYouMean()
    {
        if ($this->_result['did_you_mean'] == false) return '';

        $email = str_ireplace($this->_result['domain'], $this->_result['did_you_mean'], $this->_email);

        return $email;
    }

    public function getRequestsLeft()
    {
        return $this->_result['remaining_requests'];
    }
    
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

    private function validateResponse($response)
    {
        if (!$this->checkValidStatus($response['status'])) return;
        
        if ($response['status'] === 200) {
            
            $this->_result = $response;

            return;

        }

        $this->_result['status'] = $response['status'];
    }

    private function checkValidStatus($status)
    {
        if (empty($status) || !is_int($status)) return false;

        // expected values from api
        if ($status !== 200 && $status !== 400 && $status !== 429) return false;

        return true;
    }
}