<?php

namespace enricodias\EmailValidatorAdapter;

use GuzzleHttp\Client;

interface AdapterInterface
{
    public function __construct();

    /**
     * Validates an email address.
     *
     * @param string $email Email to be validated.
     * @param object GuzzleHttp\Client instance.
     * @return void
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
     * Checks if the email is an alias.
     * Example: test+alias@domain.com
     *
     * @return boolean true if the email is an alias.
     */
    public function isAlias();

    /**
     * Tries to suggest a correction for common typos in the email.
     *
     * @return string A possible email suggestion or an empty string.
     */
    public function didYouMean();
}