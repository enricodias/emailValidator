<?php

use PHPUnit\Framework\TestCase;
use enricodias\EmailValidator\EmailValidator;
use enricodias\EmailValidator\ServiceProviders;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

final class ValidatorPizzaTest extends TestCase
{
    /**
     * @dataProvider emailsProvider
     */
    public function testEmails($email, $isValid, $isDisposable, $isAlias, $didYouMean, $apiResponse)
    {
        $validator = $this->getMock(
            new MockHandler(
                [
                    new Response(200, [], $apiResponse),
                ]
            )
        );

        $validator->validate($email);

        $this->assertSame($isValid,      $validator->isValid());
        $this->assertSame($isDisposable, $validator->isDisposable());
        $this->assertSame($isAlias,      $validator->isAlias());
        $this->assertSame($didYouMean,   $validator->didYouMean());
    }

    /**
     * List of emails to be tested.
     * 
     * Note that validator.pizza limits the requests to 120 per hour, per IP address.
     * This package is testes in 3 PHP versions on CircleCI, 
     * therfore the tests must not exceed 40 requests on each build.
     * 
     * @codeCoverageIgnore
     */
    public function emailsProvider()
    {
        return [
            
            //email,                 isValid, isDisposable, isAlias, didYouMean,        apiResponse
            ['abc',                  false,   false,        false,   '',                '{"status":400,"error":"The email address is invalid."}'],
            ['gmail.com',            false,   false,        false,   '',                '{"status":400,"error":"The email address is invalid."}'],
            ['test@gmail.com',       true,    false,        false,   '',                '{"status":200,"email":"test@gmail.com","domain":"gmail.com","mx":true,"disposable":false,"alias":false,"did_you_mean":null,"remaining_requests":118}'],
            ['test@gmail+abc.com',   false,   false,        false,   '',                '{"status":200,"email":"test@gmail+abc.com","domain":"gmail+abc.com","mx":false,"disposable":false,"alias":true,"did_you_mean":null,"remaining_requests":117}'],
            ['test@gmail.co',        true,    false,        false,   'test@gmail.com',  '{"status":200,"email":"test@gmail.co","domain":"gmail.co","mx":false,"disposable":false,"alias":false,"did_you_mean":"gmail.com","remaining_requests":116}'],
            ['test+alias@gmail.com', true,    false,        true,    '',                '{"status":200,"email":"test+alias@gmail.com","domain":"gmail.com","mx":true,"disposable":false,"alias":true,"did_you_mean":null,"remaining_requests":115}'],
            ['abc@mailinator.com',   true,    true,         false,   '',                '{"status":200,"email":"abc@mailinator.com","domain":"mailinator.com","mx":true,"disposable":true,"alias":false,"did_you_mean":null,"remaining_requests":114}'],

        ];
    }

    public function testAddProvider()
    {
        $provider = new ServiceProviders\ValidatorPizza();

        $validator = $this->getMock(
            new MockHandler(
                [
                    new Response(200, [], '{"status":200,"email":"test@gmail.co","domain":"gmail.co","mx":false,"disposable":false,"alias":false,"did_you_mean":"gmail.com","remaining_requests":113}'),
                ]
            )
        );

        $validator->clearProviders()->addProvider($provider, 'validator.pizza')->validate('test@gmail.co');

        $this->assertSame(true,             $validator->isValid());
        $this->assertSame(false,            $validator->isDisposable());
        $this->assertSame(false,            $validator->isAlias());
        $this->assertSame('test@gmail.com', $validator->didYouMean());

        $this->assertSame(113, $validator->getRequestsLeft());
    }

    public function testRecuestsLeft()
    {
        $validator = $this->getMock(
            new MockHandler(
                [
                    new Response(200, [], '{"status":200,"email":"test@gmail.com","domain":"gmail.com","mx":true,"disposable":false,"alias":false,"did_you_mean":null,"remaining_requests":118}'),
                ]
            )
        );

        $validator->validate('test@gmail.com');

        $this->assertSame(118, $validator->getRequestsLeft());
    }
    
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
            ->setMethods(['getGuzzleClient'])
            ->getMock();
        
        $stub->method('getGuzzleClient')->willReturn($client);

        return $stub;

    }
}