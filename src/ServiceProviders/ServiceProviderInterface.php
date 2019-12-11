<?php

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
    public function __construct($apiKey);

    /**
     * Validates an email address.
     *
     * @param string $email Email to be validated.
     * @param object GuzzleHttp\Client instance.
     * @return boolean true if the service provider returns a valid response.
     */
    public function validate($email, Client $client);

    /**
     * Checks if the email is valid. Disposable emails are also valid.
     *
     * @return boolean true if the email is valid.
     */
    public function isValid();

    /**
     * Checks if the email is disposable.
     *
     * @return boolean true if the email is disposable.
     */
    public function isDisposable();

    /**
     * Tries to suggest a correction for common typos in the email.
     *
     * @return string A possible email suggestion or an empty string.
     */
    public function didYouMean();

    /**
     * Returns the number allowed requests left in the current service provider before being rate limited.
     *
     * @return int Number requests left or -1 if not supported.
     */
    public function getRequestsLeft();
}