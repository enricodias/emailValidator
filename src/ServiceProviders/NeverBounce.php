<?php

namespace enricodias\EmailValidator\ServiceProviders;

use GuzzleHttp\Psr7\Request;

/**
 * NeverBounce
 * 
 * Uses NeverBounce as a service provider to validate an email.
 * 
 * @see    https://developers.neverbounce.com/reference#single API doc.
 * 
 * @author Enrico Dias <enrico@enricodias.com>
 * @link   https://github.com/enricodias/emailValidator Github repository.
 */
class NeverBounce extends ServiceProvider implements ServiceProviderInterface
{
    /**
     * Default values returned by NeverBounce API.
     *
     * @var array
     */
    private $_result = array(
        'status'               => '',
        'result'               => 'valid',
        'flags'                => [],
        'suggested_correction' => '',   
        'execution_time'       => 0,
    );

    /**
     * Validates an email address.
     *
     * NeverBounce doesn't support aliases, the email is validated without alias.
     * 
     * @param string $email Email to be validated.
     * @param object GuzzleHttp\Client $client.
     * @return boolean true if the validation occurs.
     */
    public function validate($email, \GuzzleHttp\Client $client)
    {
        $this->_email = $email;

        $domain = strstr($email, '@');
        $email  = strstr($email, '@', true);
        $email  = strstr($email, '+', true) . $domain;

        $request = new Request(
            'GET',
            'https://api.neverbounce.com/v4/single/check',
            [
                'query' => [
                    'key'   => $this->_apiKey,
                    'email' => $email,
                ],
                'Accept' => 'application/json',
            ]
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
        if ($this->_result['result'] === 'invalid') return false;

        return true;
    }
    
    /**
     * Checks if the email is disposable.
     *
     * @return boolean true if the email is disposable.
     */
    public function isDisposable()
    {
        if ($this->_result['result'] === 'disposable') return true;

        return false;
    }

    /**
     * Tries to suggest a correction for common typos in the email.
     * 
     * Since the email is validated without alias, only the domain suggestion is valid.
     *
     * @return string A possible email suggestion or an empty string.
     */
    public function didYouMean()
    {
        if ($this->_result['suggested_correction'] === '') return '';

        if (stripos('+', $this->_email) === false) return $this->_result['suggested_correction'];

        $domain = strstr($this->_result['suggested_correction'], '@');
        $email  = strstr($this->_email, '@', true);
        $email  = strstr($email, '+', true) . $domain;

        return $email;
    }

    /**
     * Processes a response from NeverBounce API.
     *
     * @param string $response Response from NeverBounce API.
     * @return void
     */
    private function validateResponse($response)
    {
        if (array_key_exists('status', $response) && $response['status'] !== 'success') return false;

        $this->_result = array_merge($this->_result, $response);

        return true;
    }
}