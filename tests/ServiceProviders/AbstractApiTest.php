<?php

namespace enricodias\EmailValidator\Tests\ServiceProviders;

use enricodias\EmailValidator\EmailValidator;
use enricodias\EmailValidator\ServiceProviders\AbstractApi;
use enricodias\EmailValidator\Tests\EmailTest;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;

final class AbstractApiTest extends EmailTest
{
    public function getApiResponseList()
    {
        return [

            //email,                   apiResponse
            'abc'                       => '{"email":"abc","autocorrect":"","deliverability":"UNDELIVERABLE","quality_score":0.0,"is_valid_format":{"value":false,"text":"FALSE"},"is_free_email":{"value":false,"text":"FALSE"},"is_disposable_email":{"value":false,"text":"FALSE"},"is_role_email":{"value":false,"text":"FALSE"},"is_catchall_email":{"value":false,"text":"FALSE"},"is_mx_found":{"value":false,"text":"FALSE"},"is_smtp_valid":{"value":false,"text":"FALSE"}}',
            'gmail.com'                 => '{"email":"gmail.com","autocorrect":"","deliverability":"UNDELIVERABLE","quality_score":0.0,"is_valid_format":{"value":false,"text":"FALSE"},"is_free_email":{"value":false,"text":"FALSE"},"is_disposable_email":{"value":false,"text":"FALSE"},"is_role_email":{"value":false,"text":"FALSE"},"is_catchall_email":{"value":false,"text":"FALSE"},"is_mx_found":{"value":false,"text":"FALSE"},"is_smtp_valid":{"value":false,"text":"FALSE"}}',
            'john@gmail.com'            => '{"email":"john@gmail.com","autocorrect":"","deliverability":"DELIVERABLE","quality_score":0.9,"is_valid_format":{"value":true,"text":"TRUE"},"is_free_email":{"value":true,"text":"TRUE"},"is_disposable_email":{"value":false,"text":"FALSE"},"is_role_email":{"value":false,"text":"FALSE"},"is_catchall_email":{"value":false,"text":"FALSE"},"is_mx_found":{"value":true,"text":"TRUE"},"is_smtp_valid":{"value":true,"text":"TRUE"}}',
            'test@gmail+abc.com'        => '{"email":"test@gmail+abc.com","autocorrect":"","deliverability":"UNDELIVERABLE","quality_score":0.0,"is_valid_format":{"value":false,"text":"FALSE"},"is_free_email":{"value":false,"text":"FALSE"},"is_disposable_email":{"value":false,"text":"FALSE"},"is_role_email":{"value":false,"text":"FALSE"},"is_catchall_email":{"value":false,"text":"FALSE"},"is_mx_found":{"value":false,"text":"FALSE"},"is_smtp_valid":{"value":false,"text":"FALSE"}}',
            'test@gmail.co'             => '{"email":"test@gmail.co","autocorrect":"test@gmail.com","deliverability":"UNKNOWN","quality_score":0.3,"is_valid_format":{"value":true,"text":"TRUE"},"is_free_email":{"value":false,"text":"FALSE"},"is_disposable_email":{"value":false,"text":"FALSE"},"is_role_email":{"value":false,"text":"FALSE"},"is_catchall_email":{"value":false,"text":"FALSE"},"is_mx_found":{"value":false,"text":"FALSE"},"is_smtp_valid":{"value":false,"text":"FALSE"}}',
            'testvalid+alias@gmail.com' => '{"email":"testvalid+alias@gmail.com","autocorrect":"","deliverability":"DELIVERABLE","quality_score":0.92,"is_valid_format":{"value":true,"text":"TRUE"},"is_free_email":{"value":true,"text":"TRUE"},"is_disposable_email":{"value":false,"text":"FALSE"},"is_role_email":{"value":false,"text":"FALSE"},"is_catchall_email":{"value":false,"text":"FALSE"},"is_mx_found":{"value":true,"text":"TRUE"},"is_smtp_valid":{"value":true,"text":"TRUE"}}',
            'abc@mailinator.com'        => '{"email":"abc@mailinator.com","autocorrect":"","deliverability":"DELIVERABLE","quality_score":0.4,"is_valid_format":{"value":true,"text":"TRUE"},"is_free_email":{"value":false,"text":"FALSE"},"is_disposable_email":{"value":true,"text":"TRUE"},"is_role_email":{"value":false,"text":"FALSE"},"is_catchall_email":{"value":false,"text":"FALSE"},"is_mx_found":{"value":true,"text":"TRUE"},"is_smtp_valid":{"value":true,"text":"TRUE"}}',
            'test@iiron.us'             => '{"email":"test@iiron.us","autocorrect":"","deliverability":"DELIVERABLE","quality_score":0.35,"is_valid_format":{"value":true,"text":"TRUE"},"is_free_email":{"value":false,"text":"FALSE"},"is_disposable_email":{"value":true,"text":"TRUE"},"is_role_email":{"value":false,"text":"FALSE"},"is_catchall_email":{"value":false,"text":"FALSE"},"is_mx_found":{"value":true,"text":"TRUE"},"is_smtp_valid":{"value":true,"text":"TRUE"}}',

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
        $validator = $this->getInvalidApiKeyMock(
            'test@gmail.com',
            401,
            '{"error":{"message":"The API key you provided is invalid.","code":401}}'
        );

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
                            'https://emailvalidation.abstractapi.com/v1',
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
        $provider = new AbstractApi('API_KEY');

        $client = $this->buildClientWithHistory($mock);

        return parent::buildValidator($client, $provider);
    }
}
