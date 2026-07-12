<?php

namespace enricodias\EmailValidator\Tests;

use PHPUnit\Framework\TestCase;
use enricodias\EmailValidator\EmailValidator;
use enricodias\EmailValidator\ServiceProviders\ServiceProviderInterface;
use enricodias\EmailValidator\Tests\ServiceProviders\ServiceProviderTestInterface;
use \GuzzleHttp\Handler\MockHandler;
use \GuzzleHttp\Psr7\HttpFactory;
use \GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;

abstract class EmailTest extends TestCase implements ServiceProviderTestInterface
{
    /**
     * @dataProvider emailsProvider
     */
    public function testEmails($email, $isValid, $isDisposable, $didYouMean, $isHighRisk, $apiResponse)
    {
        $validator = $this->getServiceMock(
            new MockHandler(
                [
                    new Response(
                        200,
                        [],
                        $apiResponse
                    ),
                ]
            )
        );

        $validator->validate($email);

        $this->assertSame($isValid,      $validator->isValid(),      'Method: isValid()');
        $this->assertSame($isDisposable, $validator->isDisposable(), 'Method: isDisposable()');

        if ($didYouMean != '') $this->assertSame($didYouMean, $validator->didYouMean(), 'Method: didYouMean()');
    }

    /**
     * List of emails to be tested.
     * 
     * This list contains the basic validations that should be implemented in all service providers.
     * The api responses for each provider are fetched using ServiceProviders\getApiResponseList
     * 
     * @codeCoverageIgnore
     */
    public function emailsProvider()
    {
        $list = [
            
            //email,                      isValid, isDisposable, didYouMean,       apiResponse
            ['abc',                       false,   false,        '',               ''],
            ['gmail.com',                 false,   false,        '',               ''],
            ['john@gmail.com',            true,    false,        '',               ''],
            ['test@gmail+abc.com',        false,   false,        '',               ''],
            ['test@gmail.co',             true,    false,        'test@gmail.com', ''],
            ['testvalid+alias@gmail.com', true,    false,        '',               ''],
            ['abc@mailinator.com',        true,    true,         '',               ''], // disposable email in the local list
            ['test@iiron.us',             true,    true,         '',               ''], // disposable email NOT in the local list

        ];

        $apiResponseList = $this->getApiResponseList();

        foreach ($list as $key => $row) {
            
            if (array_key_exists($row[0], $apiResponseList)) $list[$key][5] = $apiResponseList[$row[0]];
            
        }

        return $list;
    }

    /**
     * Returns a list of API responses of each email for mocking.
     *
     * Implemented by each service provider test class.
     *
     * @return array list of API responses per email address, ex: ['email', 'apiResponse']
     */
    abstract public function getApiResponseList();

    /**
     * Returns an EmailValidator instance with a mocked PSR-18 HTTP client for a service provider.
     *
     * Implemented by each service provider test class.
     *
     * @param MockHandler $mock
     * @return EmailValidator
     */
    abstract public function getServiceMock(MockHandler $mock);

    protected function getInvalidApiKeyMock($email, $code, $response)
    {
        $validator = $this->getServiceMock(
            new MockHandler(
                [
                    new Response(
                        $code,
                        [],
                        $response
                    ),
                ]
            )
        );
        
        return $validator->validate($email);
    }

    protected function getValidatorMock($email)
    {
        $responseList = $this->getApiResponseList();

        return $this->getServiceMock(
            new MockHandler(
                [
                    new Response(
                        200,
                        [],
                        $responseList[$email]
                    ),
                ]
            )
        )->validate($email);
    }
    
    /**
     * Builds an EmailValidator instance using the given PSR-18 client, without any mocking involved.
     *
     * The request factory is a real GuzzleHttp\Psr7\HttpFactory instance since only the HTTP client
     * needs to be faked (via MockHandler) to control the API response.
     */
    protected function buildValidator(ClientInterface $client, ServiceProviderInterface $provider): EmailValidator
    {
        $validator = new EmailValidator($client, new HttpFactory());

        $validator->clearProviders()->addProvider($provider);

        return $validator;
    }
}