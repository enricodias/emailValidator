<?php

namespace enricodias\EmailValidator\Tests\Utils;

use enricodias\EmailValidator\ServiceProviders\ServiceProvider;
use enricodias\EmailValidator\ServiceProviders\ServiceProviderInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * A minimal service provider used to test the logging behaviour implemented in the abstract
 * ServiceProvider class, decoupled from any real service provider implementation.
 *
 * The API key is sent both as a query string parameter and as an Authorization header, so a
 * single provider is enough to exercise both redaction paths in ServiceProvider::getLogContext().
 */
final class FakeServiceProvider extends ServiceProvider implements ServiceProviderInterface
{
    /**
     * Validates an email address against a fake endpoint.
     */
    public function validate(string $email, ClientInterface $client, RequestFactoryInterface $requestFactory): bool
    {
        $this->email = $email;

        $request = $this->buildRequest(
            $requestFactory,
            'https://api.example.com/verify',
            ['email' => $email, 'apikey' => $this->apiKey],
            [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ]
        );

        return parent::request($client, $request);
    }

    public function isValid(): bool
    {
        return true;
    }

    public function isDisposable(): bool
    {
        return false;
    }

    public function didYouMean(): string
    {
        return '';
    }
}
