<?php

namespace enricodias\EmailValidator\ServiceProviders;

use GuzzleHttp\Psr7\Request;

/**
 * Kickbox
 * 
 * Uses Kickbox as a service provider to validate an email.
 * 
 * @see    https://docs.kickbox.com/docs/single-verification-api API doc.
 * 
 * @author Enrico Dias <enrico@enricodias.com>
 * @link   https://github.com/enricodias/emailValidator Github repository.
 */
class Kickbox extends ServiceProvider implements ServiceProviderInterface
{
    /**
     * Default values returned by kickbox API.
     *
     * @var array
     */
    private $_result = array(
        'result'       => 'deliverable',
        'reason'       => '',
        'role'         => false,
        'free'         => false,
        'disposable'   => false,
        'accept_all'   => false,
        'did_you_mean' => '',
        'sendex'       => 1,
        'email'        => '',
        'user'         => '',
        'domain'       => '',
        'success'      => false,
        'message'      => null,
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

        $request = new Request(
            'GET',
            'https://api.kickbox.com/v2/verify',
            [
                'query' => [
                    'email'  => $email,
                    'apikey' => $this->_apiKey
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
        if ($this->_result['result'] === 'undeliverable') return false;

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
        return (string) $this->_result['did_you_mean'];
    }
    
    /**
     * Checks if the email risk score is considered high.
     *
     * @return boolean true if the email is high risk.
     */
    public function isHighRisk()
    {
        if ($this->_result['sendex'] < 0.5) return true;
        
        return false;
    }

    /**
     * Processes a response from mailgun API.
     *
     * @param string $response Response from mailgun API.
     * @return void
     */
    private function validateResponse($response)
    {
        if (array_key_exists('success', $response) === false || $response['success'] !== true) return false;

        $this->_result = array_merge($this->_result, $response);

        return true;
    }
}