<?php

namespace enricodias\EmailValidator\Tests;

use PHPUnit\Framework\TestCase;
use enricodias\EmailValidator\EmailValidator;
use enricodias\EmailValidator\ServiceProviders\ServiceProviderInterface;
use \GuzzleHttp\Handler\MockHandler;
use \GuzzleHttp\Psr7\Response;
use GuzzleHttp\Client;

abstract class EmailTest extends TestCase
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
    
    protected function getMock(Client $client, ServiceProviderInterface $provider)
    {
        $stub = $this->getMockBuilder(EmailValidator::class)
            ->setMethods(['getGuzzleClient'])
            ->getMock();
        
        $stub->method('getGuzzleClient')->willReturn($client);
        
        $stub->clearProviders()->addProvider($provider);
        
        return $stub;
    }
}