<?php

namespace enricodias\EmailValidator\Tests\ServiceProviders;

use enricodias\EmailValidator\EmailValidator;
use enricodias\EmailValidator\ServiceProviders\Emailable;
use enricodias\EmailValidator\Tests\EmailTest;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;

final class EmailableTest extends EmailTest
{
    public function getApiResponseList()
    {
        return [

            //email,                   apiResponse
            'abc'                       => '{"email":"abc","state":"undeliverable","reason":"invalid_email","disposable":false,"did_you_mean":null,"domain":null,"score":0}',
            'gmail.com'                 => '{"email":"gmail.com","state":"undeliverable","reason":"invalid_email","disposable":false,"did_you_mean":null,"domain":null,"score":0}',
            'john@gmail.com'            => '{"email":"john@gmail.com","state":"deliverable","reason":"accepted_email","disposable":false,"did_you_mean":null,"domain":"gmail.com","score":100}',
            'test@gmail+abc.com'        => '{"email":"test@gmail+abc.com","state":"undeliverable","reason":"invalid_email","disposable":false,"did_you_mean":null,"domain":null,"score":0}',
            'test@gmail.co'             => '{"email":"test@gmail.co","state":"deliverable","reason":"accepted_email","disposable":false,"did_you_mean":"test@gmail.com","domain":"gmail.co","score":100}',
            'testvalid+alias@gmail.com' => '{"email":"testvalid+alias@gmail.com","state":"deliverable","reason":"accepted_email","disposable":false,"did_you_mean":null,"domain":"gmail.com","score":100}',
            'abc@mailinator.com'        => '{"email":"abc@mailinator.com","state":"risky","reason":"low_deliverability","disposable":true,"did_you_mean":null,"domain":"mailinator.com","score":20}',
            'test@iiron.us'             => '{"email":"test@iiron.us","state":"risky","reason":"low_deliverability","disposable":true,"did_you_mean":null,"domain":"iiron.us","score":20}',

        ];
    }

    public function testRiskAnalysis()
    {
        $validator = $this->getValidatorMock('john@gmail.com');

        $this->assertSame(false, $validator->isHighRisk());

        $validator = $this->getValidatorMock('test@iiron.us');

        $this->assertSame(true, $validator->isHighRisk());
    }

    public function testInvalidApiKey()
    {
        $validator = $this->getInvalidApiKeyMock('test@gmail.com', 200, '{"message":"Invalid API key."}');

        $this->assertSame(true, $validator->isValid());
    }

    public function testOfflineApi()
    {
        $stub = $this->getServiceMock(
            new MockHandler(
                [
                    new RequestException(
                        'Error Communicating with Server',
                        new Request(
                            'GET',
                            'https://api.emailable.com/v1/verify',
                            [
                                'query' => [
                                    'email'   => 'test@domain.com',
                                    'api_key' => 'API_KEY'
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

    public function getServiceMock(MockHandler $mock): EmailValidator
    {
        $provider = new Emailable('API_KEY');

        $client = $this->buildClientWithHistory($mock);

        return parent::buildValidator($client, $provider);
    }
}
