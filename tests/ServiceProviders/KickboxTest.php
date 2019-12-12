<?php

namespace enricodias\EmailValidator\Tests\ServiceProviders;

use PHPUnit\Framework\TestCase;
use enricodias\EmailValidator\Tests\EmailTest;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

final class KickboxTest extends EmailTest implements ServiceProviderTestInterface
{
    public function getApiResponseList()
    {
        return [

            //email,                   apiResponse
            'abc'                       => '{"success": true,"code": null,"message": null,"result": "undeliverable","reason": "rejected_email","role": false,"free": false,"disposable": false,"accept_all": false,"did_you_mean": null,"sendex": 0,"email": "abc","user": null,"domain": null}',
            'gmail.com'                 => '{"success": true,"code": null,"message": null,"result": "undeliverable","reason": "rejected_email","role": false,"free": false,"disposable": false,"accept_all": false,"did_you_mean": null,"sendex": 0,"email": "gmail.com","user": null,"domain": null}',
            'john@gmail.com'            => '{"success": true,"code": null,"message": null,"result": "deliverable","reason": "accepted_email","role": false,"free": true,"disposable": false,"accept_all": false,"did_you_mean": null,"sendex": 0.863,"email": "john@gmail.com","user": "john","domain": "gmail.com"}',
            'test@gmail+abc.com'        => '{"success": true,"code": null,"message": null,"result": "undeliverable","reason": "rejected_email","role": false,"free": false,"disposable": false,"accept_all": false,"did_you_mean": null,"sendex": 0,"email": "test@gmail abc.com","user": null}',
            'test@gmail.co'             => '{"success": true,"code": null,"message": null,"result": "deliverable","reason": "accepted_email","role": true,"free": false,"disposable": false,"accept_all": false,"did_you_mean": "test@gmail.com","sendex": 0.122,"email": "test@gmail.co","user": "test","domain": "gmail.co"}',
            'testvalid+alias@gmail.com' => '{"success": true,"code": null,"message": null,"result": "deliverable","reason": "accepted_email","role": false,"free": true,"disposable": false,"accept_all": false,"did_you_mean": null,"sendex": 0.863,"email": "testvalid@gmail.com","user": "testvalid","domain": "gmail.com"}',
            'abc@mailinator.com'        => '{"success": true,"code": null,"message": null,"result": "risky","reason": "low_quality","role": false,"free": true,"disposable": true,"accept_all": true,"did_you_mean": null,"sendex": 0,"email": "abc@mailinator.com","user": "abc","domain": "mailinator.com"}',
            'test@iiron.us'             => '{"success": true,"code": null,"message": null,"result": "risky","reason": "low_quality","role": true,"free": true,"disposable": true,"accept_all": true,"did_you_mean": null,"sendex": 0,"email": "test@iiron.us","user": "test","domain": "iiron.us"}',
    
        ];
    }

    public function testGetResponse()
    {
        $email = 'john@gmail.com';

        $response = $this->getProviderResponseMock($email);

        $this->assertSame(true, $response['success']);
    }

    public function testInvalidApiKey()
    {
        $validator = $this->getInvalidApiKeyMock('test@gmail.com', 404, '{"success": false, "message": "Invalid API key"}');

        $this->assertSame(true, $validator->isValid());
    }
    
    public function testOfflineApi()
    {
        $stub = $this->getServiceMock(
            new MockHandler(
                [
                    new \GuzzleHttp\Exception\RequestException(
                        'Error Communicating with Server',
                        new \GuzzleHttp\Psr7\Request(
                            'GET',
                            'https://api.kickbox.com/v2/verify',
                            [
                                'query' => [
                                    'email'  => 'test@domain.com',
                                    'apikey' => 'API_KEY'
                                ],
                                'Accept' => 'application/json',
                            ]
                        )
                    )
                ]
            )
        );

        $stub->validate('test@domain.com');

        $this->assertSame(true, $stub->isValid());
    }
    
    public function getServiceMock(MockHandler $mock)
    {
        $provider = new \enricodias\EmailValidator\ServiceProviders\Kickbox('API_KEY');

        $client = new \GuzzleHttp\Client([
            'handler'  => \GuzzleHttp\HandlerStack::create($mock),
            'base_uri' => 'https://api.kickbox.com/v2/verify',
        ]);

        return parent::getMock($client, $provider);
    }
}