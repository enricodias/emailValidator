<?php

use PHPUnit\Framework\TestCase;
use enricodias\EmailValidator\EmailValidator;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

final class ApiTest extends TestCase
{
    public function testOfflineApi()
    {
        $stub = $this->getMock(
            new MockHandler(
                [
                    new RequestException('Error Communicating with Server', new Request('GET', '/email/test@domain.com')),
                ]
            )
        );

        $stub->__construct();

        $stub->validate('test@domain.com');

        $this->assertSame(true, $stub->isValid());
    }

    public function testError400()
    {
        $stub = $this->getMock(
            new MockHandler(
                [
                    new Response(200, [], '{"status":400,"error":"The email address is invalid."}'),
                ]
            )
        );

        $stub->__construct();

        $stub->validate('test@domain.com');

        $this->assertSame(false, $stub->isValid());
    }
    
    private function getMock(MockHandler $mock)
    {
        $client = new Client([
            'handler'  => HandlerStack::create($mock),
            'base_uri' => 'https://www.validator.pizza/email/',
        ]);
        
        $stub = $this->getMockBuilder(EmailValidator::class)
            ->disableOriginalConstructor()
            ->setMethods(['getGuzzleClient'])
            ->getMock();
        
        $stub->method('getGuzzleClient')->willReturn($client);

        return $stub;

    }
}