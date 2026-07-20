<?php

namespace enricodias\EmailValidator\Tests\ServiceProviders;

use enricodias\EmailValidator\EmailValidator;
use enricodias\EmailValidator\ServiceProviders\DeBounce;
use enricodias\EmailValidator\Tests\EmailTest;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;

final class DeBounceTest extends EmailTest
{
    public function getApiResponseList()
    {
        return [

            //email,                   apiResponse
            'abc'                       => '{"debounce":{"email":"abc","code":"1","role":"false","free_email":"false","result":"Invalid","reason":"Syntax Error","send_transactional":"0","did_you_mean":""},"success":"1","balance":"329918"}',
            'gmail.com'                 => '{"debounce":{"email":"gmail.com","code":"1","role":"false","free_email":"false","result":"Invalid","reason":"Syntax Error","send_transactional":"0","did_you_mean":""},"success":"1","balance":"329918"}',
            'john@gmail.com'            => '{"debounce":{"email":"john@gmail.com","code":"5","role":"false","free_email":"true","result":"Safe to Send","reason":"Deliverable","send_transactional":"1","did_you_mean":""},"success":"1","balance":"329918"}',
            'test@gmail+abc.com'        => '{"debounce":{"email":"test@gmail+abc.com","code":"1","role":"false","free_email":"false","result":"Invalid","reason":"Syntax Error","send_transactional":"0","did_you_mean":""},"success":"1","balance":"329918"}',
            'test@gmail.co'             => '{"debounce":{"email":"test@gmail.co","code":"5","role":"false","free_email":"false","result":"Safe to Send","reason":"Deliverable","send_transactional":"1","did_you_mean":"test@gmail.com"},"success":"1","balance":"329917"}',
            'testvalid+alias@gmail.com' => '{"debounce":{"email":"testvalid+alias@gmail.com","code":"5","role":"false","free_email":"true","result":"Safe to Send","reason":"Deliverable","send_transactional":"1","did_you_mean":""},"success":"1","balance":"329916"}',
            'abc@mailinator.com'        => '{"debounce":{"email":"abc@mailinator.com","code":"3","role":"false","free_email":"false","result":"Risky","reason":"Disposable","send_transactional":"0","did_you_mean":""},"success":"1","balance":"329915"}',
            'test@iiron.us'             => '{"debounce":{"email":"test@iiron.us","code":"3","role":"false","free_email":"false","result":"Risky","reason":"Disposable","send_transactional":"0","did_you_mean":""},"success":"1","balance":"329915"}',

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
        $validator = $this->getInvalidApiKeyMock('test@gmail.com', 200, '{"success":"0","error":"Invalid API Key"}');

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
                            'https://api.debounce.io/v1/',
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
        $provider = new DeBounce('API_KEY');

        $client = $this->buildClientWithHistory($mock);

        return parent::buildValidator($client, $provider);
    }
}
