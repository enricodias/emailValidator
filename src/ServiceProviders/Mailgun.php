<?php

namespace enricodias\EmailValidator\ServiceProviders;

use GuzzleHttp\Psr7\Request;

/**
 * Mailgun
 * 
 * Uses Mailgun as a service provider to validate an email.
 * 
 * @see    https://documentation.mailgun.com/en/latest/api-email-validation.html API doc.
 * 
 * @author Enrico Dias <enrico@enricodias.com>
 * @link   https://github.com/enricodias/emailValidator Github repository.
 */
class Mailgun extends ServiceProvider implements ServiceProviderInterface
{
    /**
     * Default values returned by mailgun API.
     *
     * @var array
     */
    private $_result = array(
        'address'               => '',
        'did_you_mean'          => null,
        'is_disposable_address' => false,
        'is_role_address'       => false,
        'reason'                => [],
        'result'                => 'deliverable',
        'risk'                  => 'low',
    );
    
    /**
    * Returns the number free requests left in the current month.
    *
    * ! Apparently this is currently not supported by Mailgun API.
    *
    * @return int Number requests left.
    */
   public function getRequestsLeft()
   {
       return -1;
   }

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
            'https://api.mailgun.net/v4/address/validate',
            [
                'auth' => [
                    'api:'.$this->_apiKey,
                ],
                'query' => [
                    'address' => $email,
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
        return $this->_result['is_disposable_address'];
    }

    /**
     * Tries to suggest a correction for common typos in the email.
     *
     * ! Currently Mailgun never returns a suggestion.
     * 
     * @return string A possible email suggestion or an empty string.
     */
    public function didYouMean()
    {
        if ($this->_result['did_you_mean'] === null) return '';

        return $this->_result['did_you_mean'];
    }

    /**
     * Processes a response from mailgun API.
     *
     * @param string $response Response from mailgun API.
     * @return void
     */
    private function validateResponse($response)
    {
        $validResults = ['undeliverable', 'deliverable', 'do_not_send'];

        if (in_array($response['result'], $validResults, true) === false) return false;

        $this->_result = array_merge($this->_result, $response);

        return true;
    }
}