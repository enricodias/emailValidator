<?php

namespace enricodias\EmailValidator\Tests\ServiceProviders;

use enricodias\EmailValidator\EmailValidator;
use enricodias\EmailValidator\ServiceProviders\MillionVerifier;
use enricodias\EmailValidator\Tests\EmailTest;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;

final class MillionVerifierTest extends EmailTest
{
    public function getApiResponseList()
    {
        return [

            //email,                   apiResponse
            'abc'                       => '{"email":"abc","quality":"bad","result":"invalid","resultcode":6,"subresult":"syntax_error","free":false,"role":false,"didyoumean":"","credits":3454,"executiontime":1,"error":"","livemode":true}',
            'gmail.com'                 => '{"email":"gmail.com","quality":"bad","result":"invalid","resultcode":6,"subresult":"syntax_error","free":false,"role":false,"didyoumean":"","credits":3454,"executiontime":1,"error":"","livemode":true}',
            'john@gmail.com'            => '{"email":"john@gmail.com","quality":"good","result":"ok","resultcode":1,"subresult":"deliverable","free":true,"role":false,"didyoumean":"","credits":3453,"executiontime":250,"error":"","livemode":true}',
            'test@gmail+abc.com'        => '{"email":"test@gmail+abc.com","quality":"bad","result":"invalid","resultcode":6,"subresult":"syntax_error","free":false,"role":false,"didyoumean":"","credits":3453,"executiontime":1,"error":"","livemode":true}',
            'test@gmail.co'             => '{"email":"test@gmail.co","quality":"good","result":"ok","resultcode":1,"subresult":"deliverable","free":false,"role":false,"didyoumean":"test@gmail.com","credits":3452,"executiontime":230,"error":"","livemode":true}',
            'testvalid+alias@gmail.com' => '{"email":"testvalid+alias@gmail.com","quality":"good","result":"ok","resultcode":1,"subresult":"deliverable","free":true,"role":false,"didyoumean":"","credits":3451,"executiontime":240,"error":"","livemode":true}',
            'abc@mailinator.com'        => '{"email":"abc@mailinator.com","quality":"bad","result":"disposable","resultcode":5,"subresult":"deliverable","free":false,"role":false,"didyoumean":"","credits":3450,"executiontime":210,"error":"","livemode":true}',
            'test@iiron.us'             => '{"email":"test@iiron.us","quality":"bad","result":"disposable","resultcode":5,"subresult":"deliverable","free":false,"role":false,"didyoumean":"","credits":3449,"executiontime":220,"error":"","livemode":true}',

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
        $validator = $this->getInvalidApiKeyMock('test@gmail.com', 200, '{"error":"invalid_api_key"}');

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
                            'https://api.millionverifier.com/api/v3',
                            [
                                'query' => [
                                    'email' => 'test@domain.com',
                                    'api'   => 'API_KEY'
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
        $provider = new MillionVerifier('API_KEY');

        $client = $this->buildClientWithHistory($mock);

        return parent::buildValidator($client, $provider);
    }
}
