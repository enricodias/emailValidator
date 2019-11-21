<?php

use enricodias\EmailValidator;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;

final class ApiTest extends TestCase
{
    public function testOfflineApi()
    {
        $mock = new MockHandler([
            new RequestException('Error Communicating with Server', new Request('GET', '/email/test@domain.com'))
        ]);
        
        $client = new Client([
            'handler'  => HandlerStack::create($mock),
            'base_uri' => 'https://www.validator.pizza/email/',
        ]);
        
        $stub = $this->getMockBuilder(EmailValidator::class)
            ->disableOriginalConstructor()
            ->setMethods(['getGuzzleClient'])
            ->getMock();
        
        $stub->method('getGuzzleClient')->willReturn($client);

        $stub->__construct('test@domain.com');

        $this->assertSame(true, $stub->isValid());
    }

    public function testError400()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"status":400,"error":"The email address is invalid."}'),
        ]);
        
        $client = new Client([
            'handler'  => HandlerStack::create($mock),
            'base_uri' => 'https://www.validator.pizza/email/',
        ]);
        
        $stub = $this->getMockBuilder(EmailValidator::class)
            ->disableOriginalConstructor()
            ->setMethods(['getGuzzleClient'])
            ->getMock();
        
        $stub->method('getGuzzleClient')->willReturn($client);

        $stub->__construct('test@domain.com');

        $this->assertSame(false, $stub->isValid());
    }
}