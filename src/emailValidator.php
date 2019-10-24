<?php

namespace enricodias;

class EmailValidator {

    private $_email;

    private $_result = array(
        'status' => 0,
        'domain' => '',
        'mx' => false,
        'disposable' => false,
        'did_you_mean' => false,
        'remaining_requests' => 0
    );

    public function __construct($email) {

        if (!\Respect\Validation\Validator::email()->validate($email)) return;

        $this->_email = strtolower($email);

        $this->fetchValidatorPizza();

    }

    public function isValid() {
        
        if (empty($this->_email)) return false;

        // the email could be valid if we get any status other than 400
        if ($this->result['status'] === 400 || $this->result['mx'] === false) return false;

        return true;

    }

    public function isDisposable() {

        return $this->_result['disposable'];

    }

    // example user+alias@gmail.com
    public function isAlias() {

        return $this->_result['alias'];

    }

    public function didYouMean() {

        if ($this->_result['did_you_mean'] == false) return false;

        $email = str_ireplace($this->_result['domain'], $this->_result['did_you_mean'], $this->_email);

        return $email;

    }
    
    private function fetchValidatorPizza() {

        $curl = curl_init();

        $options = array(
            CURLOPT_URL => 'https://www.validator.pizza/email/'.$this->_email,
            CURLOPT_RETURNTRANSFER => true
        );

        curl_setopt_array($curl, $options);

        $response = json_decode(curl_exec($curl), true);

        curl_close($curl);
        
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

        // valid values
        if (empty($status) || !is_int($status)) return false;

        // expected values
        if ($status !== 200 && $status !== 400 && $status !== 429) return false;

        return true;

    }

}

?>