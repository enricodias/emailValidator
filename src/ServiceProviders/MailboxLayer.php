<?php

namespace enricodias\EmailValidator\ServiceProviders;

use GuzzleHttp\Psr7\Request;

/**
 * MailboxLayer
 * 
 * Uses MailboxLayer as a service provider to validate an email.
 * 
 * @see    https://mailboxlayer.com/documentation API doc.
 * 
 * @author Enrico Dias <enrico@enricodias.com>
 * @link   https://github.com/enricodias/emailValidator Github repository.
 */
class MailboxLayer extends ServiceProvider implements ServiceProviderInterface
{
    /**
     * Default values returned by MailboxLayer API.
     *
     * @var array
     */
    private $_result = array(
        'email'        => '',
        'did_you_mean' => '',
        'user'         => '',
        'domain'       => '',
        'format_valid' => true,
        'mx_found'     => false,
        'smtp_check'   => false,
        'catch_all'    => false,
        'role'         => false,
        'disposable'   => false,
        'free'         => false,
        'score'        => 0,
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
            'https://apilayer.net/api/check',
            [
                'query' => [
                    'email'  => $email,
                    'access_key' => $this->_apiKey
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
        return $this->_result['format_valid'];
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
        if ($this->_result['score'] < 0.5) return true;
        
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
        if (array_key_exists('format_valid', $response) === false) return false;

        $this->_result = array_merge($this->_result, $response);

        return true;
    }
}