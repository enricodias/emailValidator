<?php

namespace enricodias\EmailValidator\Tests\ServiceProviders;

use PHPUnit\Framework\TestCase;
use enricodias\EmailValidator\Tests\EmailTest;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

final class ValidatorPizzaTest extends EmailTest implements ServiceProviderTestInterface
{
    public function getApiResponseList()
    {
        return [

            //email,                   apiResponse
            'abc'                       => '{"status":400,"error":"The email address is invalid."}',
            'gmail.com'                 => '{"status":400,"error":"The email address is invalid."}',
            'john@gmail.com'            => '{"status":200,"email":"john@gmail.com","domain":"gmail.com","mx":true,"disposable":false,"alias":false,"did_you_mean":null,"remaining_requests":118}',
            'test@gmail+abc.com'        => '{"status":200,"email":"test@gmail+abc.com","domain":"gmail+abc.com","mx":false,"disposable":false,"alias":true,"did_you_mean":null,"remaining_requests":117}',
            'test@gmail.co'             => '{"status":200,"email":"test@gmail.co","domain":"gmail.co","mx":false,"disposable":false,"alias":false,"did_you_mean":"gmail.com","remaining_requests":116}',
            'testvalid+alias@gmail.com' => '{"status":200,"email":"testvalid+alias@gmail.com","domain":"gmail.com","mx":true,"disposable":false,"alias":true,"did_you_mean":null,"remaining_requests":115}',
            'abc@mailinator.com'        => '{"status":200,"email":"abc@mailinator.com","domain":"mailinator.com","mx":true,"disposable":true,"alias":false,"did_you_mean":null,"remaining_requests":114}',
            'test@iiron.us'             => '{"status":200,"email":"test@iiron.us","domain":"iiron.us","mx":true,"disposable":true,"alias":false,"did_you_mean":null,"remaining_requests":113}',
    
        ];
    }
    
    public function testOfflineApi()
    {
        $stub = $this->getServiceMock(
            new MockHandler(
                [
                    new \GuzzleHttp\Exception\RequestException('Error Communicating with Server',
                    new \GuzzleHttp\Psr7\Request('GET', '/email/test@domain.com')),
                ]
            )
        );

        $stub->validate('test@domain.com');

        $this->assertSame(true, $stub->isValid());
    }

    public function testError400()
    {
        $stub = $this->getServiceMock(
            new MockHandler(
                [
                    new Response(
                        200,
                        [],
                        '{"status":400,"error":"The email address is invalid."}'
                    ),
                ]
            )
        );

        $stub->validate('test@domain.com');

        $this->assertSame(false, $stub->isValid());
    }
    
    public function getServiceMock(MockHandler $mock)
    {
        $provider = new \enricodias\EmailValidator\ServiceProviders\ValidatorPizza();

        $client = new \GuzzleHttp\Client([
            'handler'  => \GuzzleHttp\HandlerStack::create($mock),
            'base_uri' => 'https://www.validator.pizza/email/',
        ]);

        return parent::getMock($client, $provider);
    }
}