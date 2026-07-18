<?php

declare(strict_types=1);

namespace enricodias\EmailValidator\ServiceProviders;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * ServiceProviderInterface
 *
 * Interface used to implement service providers.
 */
interface ServiceProviderInterface
{
    /**
     * Creates a new adapter instance.
     */
    public function __construct(string $apiKey);

    /**
     * Validates an email address.
     *
     * @return boolean true if the service provider returns a valid response.
     */
    public function validate(string $email, ClientInterface $client, RequestFactoryInterface $requestFactory): bool;

    /**
     * Checks if the email is valid. Disposable emails are also valid.
     */
    public function isValid(): bool;

    /**
     * Checks if the email is disposable.
     */
    public function isDisposable(): bool;

    /**
     * Tries to suggest a correction for common typos in the email.
     *
     * @return string A possible email suggestion or an empty string.
     */
    public function didYouMean(): string;

    /**
     * Returns the last valid response received by the service provider.
     *
     * @return array|null parsed json with the last valid response, or null if no request has succeeded yet.
     */
    public function getResponse(): ?array;
}
