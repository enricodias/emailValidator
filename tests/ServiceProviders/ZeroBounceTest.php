<?php

namespace enricodias\EmailValidator\Tests\ServiceProviders;

use enricodias\EmailValidator\EmailValidator;
use enricodias\EmailValidator\ServiceProviders\ZeroBounce;
use enricodias\EmailValidator\Tests\EmailTest;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;

final class ZeroBounceTest extends EmailTest
{
    public function getApiResponseList()
    {
        return [

            //email,                   apiResponse
            'abc'                       => '{"address":"abc","status":"invalid","sub_status":"failed_syntax_check","free_email":false,"catchall_domain":false,"did_you_mean":null,"account":null,"domain":null,"mx_found":"false","mx_record":null,"processed_at":"2026-01-01 00:00:00.000"}',
            'gmail.com'                 => '{"address":"gmail.com","status":"invalid","sub_status":"failed_syntax_check","free_email":false,"catchall_domain":false,"did_you_mean":null,"account":null,"domain":null,"mx_found":"false","mx_record":null,"processed_at":"2026-01-01 00:00:00.000"}',
            'john@gmail.com'            => '{"address":"john@gmail.com","status":"valid","sub_status":"","free_email":true,"catchall_domain":false,"did_you_mean":null,"account":"john","domain":"gmail.com","mx_found":"true","mx_record":"gmail-smtp-in.l.google.com","processed_at":"2026-01-01 00:00:00.000"}',
            'test@gmail+abc.com'        => '{"address":"test@gmail+abc.com","status":"invalid","sub_status":"failed_syntax_check","free_email":false,"catchall_domain":false,"did_you_mean":null,"account":null,"domain":null,"mx_found":"false","mx_record":null,"processed_at":"2026-01-01 00:00:00.000"}',
            'test@gmail.co'             => '{"address":"test@gmail.co","status":"valid","sub_status":"","free_email":false,"catchall_domain":false,"did_you_mean":"test@gmail.com","account":"test","domain":"gmail.co","mx_found":"true","mx_record":"gmail.co","processed_at":"2026-01-01 00:00:00.000"}',
            'testvalid+alias@gmail.com' => '{"address":"testvalid+alias@gmail.com","status":"valid","sub_status":"","free_email":true,"catchall_domain":false,"did_you_mean":null,"account":"testvalid+alias","domain":"gmail.com","mx_found":"true","mx_record":"gmail-smtp-in.l.google.com","processed_at":"2026-01-01 00:00:00.000"}',
            'abc@mailinator.com'        => '{"address":"abc@mailinator.com","status":"do_not_mail","sub_status":"disposable","free_email":false,"catchall_domain":true,"did_you_mean":null,"account":"abc","domain":"mailinator.com","mx_found":"true","mx_record":"mailinator.com","processed_at":"2026-01-01 00:00:00.000"}',
            'test@iiron.us'             => '{"address":"test@iiron.us","status":"do_not_mail","sub_status":"disposable","free_email":false,"catchall_domain":true,"did_you_mean":null,"account":"test","domain":"iiron.us","mx_found":"true","mx_record":"iiron.us","processed_at":"2026-01-01 00:00:00.000"}',

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
        $validator = $this->getInvalidApiKeyMock('test@gmail.com', 200, '{"error":"Invalid API Key or your account ran out of credits"}');

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
                            'https://api.zerobounce.net/v2/validate',
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
        $provider = new ZeroBounce('API_KEY');

        $client = $this->buildClientWithHistory($mock);

        return parent::buildValidator($client, $provider);
    }
}
