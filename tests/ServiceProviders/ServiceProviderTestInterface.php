<?php

namespace enricodias\EmailValidator\Tests\ServiceProviders;

use enricodias\EmailValidator\EmailValidator;
use GuzzleHttp\Handler\MockHandler;

interface ServiceProviderTestInterface
{
    /**
     * Default method for testing emails.
     *
     * @see \enricodias\EmailValidator\Tests\EmailTest::testEmails()
     */
    public function testEmails(string $email, bool $isValid, bool $isDisposable, bool $isAlias, string $didYouMean, string $apiResponse): void;

    /**
     * Email data provider.
     *
     * @see \enricodias\EmailValidator\Tests\EmailTest::emailsProvider()
     */
    public function emailsProvider(): array;

    /**
     * Returns a list of API responses of each email for mocking.
     *
     * @see \emailsTest::emailsProvider()
     *
     * @return array list of API responses per email address, ex: ['email', 'apiResponse']
     */
    public function getApiResponseList();

    /**
     * Returns a GuzzleHttp mock to be used in a service provider.
     */
    public function getServiceMock(MockHandler $mock): EmailValidator;
}
