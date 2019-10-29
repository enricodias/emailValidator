<?php

namespace enricodias;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class EmailValidator
{
    private $_email;

    private $_result = array(
        'status'       => 0,
        'domain'       => '',
        'mx'           => false,
        'disposable'   => false,
        'alias'        => false,
        'did_you_mean' => false,
    );

    public function __construct($email) {

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) return;

        $this->_email = strtolower($email);

        $this->fetchValidatorPizza();

    }

    public function isValid() {
        
        if (empty($this->_email)) return false;

        if ($this->_result['status'] !== 0) {

            // the email could be valid if we get any status other than 400
            if ($this->_result['status'] === 400 || $this->_result['mx'] === false) return false;

        }

        return true;

    }

    public function isDisposable() {

        return $this->_result['disposable'];

    }

    public function isAlias() {

        return $this->_result['alias'];

    }

    public function didYouMean() {

        if ($this->_result['did_you_mean'] == false) return '';

        $email = str_ireplace($this->_result['domain'], $this->_result['did_you_mean'], $this->_email);

        return $email;

    }
    
    private function fetchValidatorPizza() {
        
        $client = new Client([
            'base_uri' => 'https://www.validator.pizza/email/',
        ]);

        $request = new Request('GET', $this->_email, [
            'Accept' => 'application/json',
        ]);

        try {

            $response = $client->send($request);

        } catch (\Exception $e) {
            
            return;
            
        }

        $response = json_decode($response->getBody(), true);
        
        if (json_last_error() != JSON_ERROR_NONE) return;

        $this->validateResponse($response);

    }

    private function validateResponse($response) {

        if (!$this->checkValidStatus($response['status'])) return;
        
        if ($response['status'] === 200) {
            
            $this->_result = $response;

            return;

        }

        $this->_result['status'] = $response['status'];

    }

    private function checkValidStatus($status) {

        if (empty($status) || !is_int($status)) return false;

        // expected values from api
        if ($status !== 200 && $status !== 400 && $status !== 429) return false;

        return true;

    }

}
