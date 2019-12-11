<?php

namespace enricodias\EmailValidator\ServiceProviders;

/**
 * An abstract class with common methods used by multiple service providers.
 */
abstract class ServiceProvider
{
    /**
     * The service provider API key.
     *
     * @var string
     */
    protected $_apiKey = '';

    /**
     * Email to be validated.
     *
     * @var string
     */
    protected $_email = '';

    /**
     * The last valid request response.
     *
     * @var array
     */
    private $_response;

    /**
     * Creates a new service provider instance.
     * 
     * @param string $apiKey Optional API Key.
     * @return void
     */
    public function __construct($apiKey = '')
    {
        $this->_apiKey = $apiKey;
    }

    /**
     * Make a request and expects a json response.
     *
     * @param \GuzzleHttp\Client $client
     * @param \GuzzleHttp\Psr7\Request $request
     * @return boolean true if the response is a valid json.
     */
    protected function request(\GuzzleHttp\Client $client, \GuzzleHttp\Psr7\Request $request)
    {
        try {

            $response = $client->send($request);

        } catch (\Exception $e) {
            
            return false;
            
        }

        $response = json_decode($response->getBody(), true);
        
        if (json_last_error() != JSON_ERROR_NONE) return false;

        $this->_response = $response;

        return true;
    }

    /**
     * Returns the last request response
     *
     * @return array parsed json of the request response
     */
    protected function getResponse()
    {
        return $this->_response;
    }
}