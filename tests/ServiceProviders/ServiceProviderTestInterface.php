<?php

namespace enricodias\EmailValidator\Tests\ServiceProviders;

interface ServiceProviderTestInterface
{
    /**
     * Default method for testing emails.
     *
     * @see \enricodias\EmailValidator\Tests\EmailTest::testEmails()
     * 
     * @param string  $email        email being tested.
     * @param boolean $isValid      true if the email is valid.
     * @param boolean $isDisposable true if the email is disposable.
     * @param boolean $isAlias      true if the email is an alias.
     * @param string  $didYouMean   a possible email suggestion or an empty string.
     * @param string  $apiResponse  string to be mocked as the API response.
     * @return void
     */
    public function testEmails($email, $isValid, $isDisposable, $isAlias, $didYouMean, $apiResponse);

    /**
     * Email data provider.
     * 
     * @see \enricodias\EmailValidator\Tests\EmailTest::emailsProvider()
     * 
     * @return array
     */
    public function emailsProvider();

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
     *
     * @param \GuzzleHttp\Handler\MockHandler $mock
     * @return \enricodias\EmailValidator
     */
    public function getServiceMock(\GuzzleHttp\Handler\MockHandler $mock);
}