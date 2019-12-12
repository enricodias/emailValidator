<?php

namespace enricodias\EmailValidator\ServiceProviders;

/**
 * ValidatorPizza
 * 
 * Uses validator.pizza as a service provider to validate an email.
 * 
 * @see    https://www.validator.pizza/ validator.pizza API.
 * 
 * @author Enrico Dias <enrico@enricodias.com>
 * @link   https://github.com/enricodias/emailValidator Github repository.
 */
class ValidatorPizza extends ServiceProvider implements ServiceProviderInterface
{
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
        'did_you_mean'       => null,
        'remaining_requests' => 120,
    );

    /**
     * Validates an email address.
     *
     * @param string $email Email to be validated.
     * @param object GuzzleHttp\Client $client.
     * @return boolean true if the validation occurs.
     */
    public function validate($email, \GuzzleHttp\Client $client)
    {
        $this->_email = $email;

        $request = new \GuzzleHttp\Psr7\Request(
            'GET',
            'https://www.validator.pizza/email/'.$email,
            ['Accept' => 'application/json']
        );

        if (parent::request($client, $request) === false) return false;

        return $this->validateResponse(parent::getResponse());
    }

    /**
     * Checks if the email is valid. Disposable emails are also valid.
     *
     * @return boolean true if the email is valid.
     */
    public function isValid()
    {
        if ($this->_result['status'] !== 0) {

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
     * Tries to suggest a correction for common typos in the email.
     *
     * @return string A possible email suggestion or an empty string.
     */
    public function didYouMean()
    {
        if ($this->_result['did_you_mean'] === null) return '';

        $email = str_ireplace($this->_result['domain'], $this->_result['did_you_mean'], $this->_email);

        return $email;
    }

    /**
     * Processes a response from validator.pizza's API.
     *
     * @param string $response Response from validator.pizza's API.
     * @return void
     */
    private function validateResponse($response)
    {
        if (array_key_exists('status', $response) === false || !$this->checkValidStatus($response['status'])) return false;

        $this->_result['status'] = $response['status'];
        
        if ($response['status'] === 200) $this->_result = $response;

        return true;
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