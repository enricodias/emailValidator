<?php

declare(strict_types=1);

namespace enricodias\EmailValidator\ServiceProviders;

use GuzzleHttp\Client;

/**
 * ServiceProviderInterface
 * 
 * Interface used to implement service providers.
 * 
 * @author Enrico Dias <enrico@enricodias.com>
 */
interface ServiceProviderInterface
{
    /**
     * Creates a new adapter instance.
     *
     * @param string $apiKey Optional API Key.
     * @return void
     */
    public function __construct(string $apiKey);

    /**
     * Validates an email address.
     *
     * @param string $email Email to be validated.
     * @param object GuzzleHttp\Client instance.
     * @return boolean true if the service provider returns a valid response.
     */
    public function validate(string $email, Client $client): bool;

    /**
     * Checks if the email is valid. Disposable emails are also valid.
     *
     * @return boolean true if the email is valid.
     */
    public function isValid(): bool;

    /**
     * Checks if the email is disposable.
     *
     * @return boolean true if the email is disposable.
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