<?php

declare(strict_types=1);

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
    protected $apiKey = '';

    /**
     * Email to be validated.
     *
     * @var string
     */
    protected $email = '';

    /**
     * The last valid response received by the service provider.
     *
     * @var array
     */
    private $result;

    /**
     * Creates a new service provider instance.
     *
     * @param string $apiKey Optional API Key.
     * @return void
     */
    public function __construct(string $apiKey = '')
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Make a request and expects a json response.
     *
     * @param \GuzzleHttp\Client $client
     * @param \GuzzleHttp\Psr7\Request $request
     * @return boolean true if the response is a valid json.
     */
    protected function request(\GuzzleHttp\Client $client, \GuzzleHttp\Psr7\Request $request): bool
    {
        try {

            $response = $client->send($request);

            $this->result = \json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        } catch (\Exception $e) {

            return false;

        }

        return true;
    }

    /**
     * Returns the last request response.
     *
     * @return array|null parsed json of the request response, or null if no request has succeeded yet.
     */
    public function getResponse(): ?array
    {
        return $this->result;
    }
}
