<?php

declare(strict_types=1);

namespace enricodias\EmailValidator\ServiceProviders;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * An abstract class with common methods used by multiple service providers.
 */
abstract class ServiceProvider implements LoggerAwareInterface
{
    /**
     * Provides the $logger property and the setLogger() method.
     *
     * @see LoggerAwareTrait::$logger PSR-3 logger instance.
     * @see LoggerAwareTrait::setLogger() Sets the PSR-3 logger instance.
     */
    use LoggerAwareTrait;

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
     * @var int|null
     */
    private $responseStatusCode;

    /**
     * @var array<string, string[]>
     */
    private $responseHeaders = [];

    /**
     * Creates a new service provider instance.
     */
    public function __construct(string $apiKey = '')
    {
        $this->apiKey = $apiKey;
        $this->logger = new NullLogger();
    }

    /**
     * Builds a GET request with an optional query string and headers.
     *
     * @param RequestFactoryInterface $requestFactory PSR-17 request factory used to build the request.
     * @param string $uri Base URI, without a query string.
     * @param array $query Query string parameters.
     * @param array $headers Request headers, keyed by header name.
     */
    protected function buildRequest(RequestFactoryInterface $requestFactory, string $uri, array $query = [], array $headers = []): RequestInterface
    {
        if (\count($query) > 0) $uri .= '?' . \http_build_query($query);

        $request = $requestFactory->createRequest('GET', $uri);

        return $this->buildWithHeaders($request, $headers);
    }

    /**
     * Builds a POST request with a JSON encoded body and headers.
     *
     * @param RequestFactoryInterface $requestFactory PSR-17 request factory used to build the request.
     * @param string $uri Request URI.
     * @param array $body Data to be JSON encoded and sent as the request body.
     * @param array $headers Request headers, keyed by header name.
     */
    protected function buildJsonRequest(RequestFactoryInterface $requestFactory, string $uri, array $body = [], array $headers = []): RequestInterface
    {
        $request = $requestFactory->createRequest('POST', $uri);

        $request->getBody()->write((string) \json_encode($body));

        $headers['Content-Type'] = 'application/json';

        return $this->buildWithHeaders($request, $headers);
    }

    /**
     * Inject http headers in a request
     */
    private function buildWithHeaders(RequestInterface $request, array $headers = []): RequestInterface
    {
        foreach ($headers as $name => $value) {

            $request = $request->withHeader($name, $value);

        }

        return $request;
    }

    /**
     * Make a request and expects a json response.
     *
     * @param ClientInterface $client PSR-18 HTTP client.
     * @param RequestInterface $request PSR-7 request.
     *
     * @return boolean true if the response is a valid json.
     */
    protected function request(ClientInterface $client, RequestInterface $request): bool
    {
        $context = $this->getLogContext($request);
        $this->result = null;
        $this->responseStatusCode = null;
        $this->responseHeaders = [];

        try {

            $response = $client->sendRequest($request);

            $this->responseStatusCode = $response->getStatusCode();
            $this->responseHeaders = $response->getHeaders();

            $this->result = \json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        } catch (ClientExceptionInterface $e) {

            $this->logger->debug('Service provider request failed.', \array_merge($context, ['exception' => $e->getMessage()]));

            return false;

        } catch (\JsonException $e) {

            $this->logger->debug('Service provider returned an invalid JSON response.', \array_merge($context, ['exception' => $e->getMessage()]));

            return false;

        }

        $this->logger->debug('Service provider request succeeded.', $context);

        return true;
    }

    /**
     * Builds a redacted log context from a request, masking the API key from the URI and headers.
     *
     * @param RequestInterface $request PSR-7 request.
     *
     * @return array{method: string, uri: string, headers: array}
     */
    private function getLogContext(RequestInterface $request): array
    {
        $uri = (string) $request->getUri();

        if ($this->apiKey !== '') $uri = \str_replace($this->apiKey, '***REDACTED***', $uri);

        $headers = [];

        foreach ($request->getHeaders() as $name => $values) {

            $name = (string) $name;

            if (\strtolower($name) === 'authorization') {

                $headers[$name] = '***REDACTED***';

                continue;

            }

            $value = \implode(', ', $values);

            if ($this->apiKey !== '') $value = \str_replace($this->apiKey, '***REDACTED***', $value);

            $headers[$name] = $value;

        }

        return [
            'method'  => $request->getMethod(),
            'uri'     => $uri,
            'headers' => $headers,
        ];
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

    /**
     * Returns the status code from the most recent parsed response.
     */
    protected function getResponseStatusCode(): ?int
    {
        return $this->responseStatusCode;
    }

    /**
     * Returns the headers from the most recent parsed response.
     *
     * @return array<string, string[]>
     */
    protected function getResponseHeaders(): array
    {
        return $this->responseHeaders;
    }

    protected function getQuotaCacheIdentity(): string
    {
        return static::class . ':' . \hash('sha256', $this->apiKey);
    }

    protected function nextUtcMonth(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('first day of next month 00:00:00', new \DateTimeZone('UTC'));
    }
}
