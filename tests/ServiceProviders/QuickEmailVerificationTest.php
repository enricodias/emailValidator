<?php

namespace enricodias\EmailValidator\Tests\ServiceProviders;

use enricodias\EmailValidator\Tests\EmailTest;
use GuzzleHttp\Handler\MockHandler;

final class QuickEmailVerificationTest extends EmailTest
{
    public function getApiResponseList()
    {
        return [

            //email,                   apiResponse
            'abc'                       => '{"result":"invalid","reason":"invalid_email","disposable":"false","accept_all":"false","role":"false","free":"false","email":"abc","user":"abc","domain":"","mx_record":"","mx_domain":"","safe_to_send":"false","did_you_mean":"","success":"true","message":""}',
            'gmail.com'                 => '{"result":"invalid","reason":"invalid_email","disposable":"false","accept_all":"false","role":"false","free":"false","email":"gmail.com","user":"","domain":"gmail.com","mx_record":"","mx_domain":"","safe_to_send":"false","did_you_mean":"","success":"true","message":""}',
            'john@gmail.com'            => '{"result":"valid","reason":"accepted_email","disposable":"false","accept_all":"false","role":"false","free":"true","email":"john@gmail.com","user":"john","domain":"gmail.com","mx_record":"gmail-smtp-in.l.google.com","mx_domain":"google.com","safe_to_send":"true","did_you_mean":"","success":"true","message":""}',
            'test@gmail+abc.com'        => '{"result":"invalid","reason":"invalid_domain","disposable":"false","accept_all":"false","role":"false","free":"false","email":"test@gmail abc.com","user":"test","domain":"gmail abc.com","mx_record":"","mx_domain":"","safe_to_send":"false","did_you_mean":"","success":"true","message":""}',
            'test@gmail.co'             => '{"result":"unknown","reason":"no_connect","disposable":"false","accept_all":"false","role":"true","free":"false","email":"test@gmail.co","user":"test","domain":"gmail.co","mx_record":"","mx_domain":"","safe_to_send":"false","did_you_mean":"test@gmail.com","success":"true","message":""}',
            'testvalid+alias@gmail.com' => '{"result":"valid","reason":"accepted_email","disposable":"false","accept_all":"false","role":"false","free":"true","email":"testvalid@gmail.com","user":"testvalid","domain":"gmail.com","mx_record":"gmail-smtp-in.l.google.com","mx_domain":"google.com","safe_to_send":"true","did_you_mean":"","success":"true","message":""}',
            'abc@mailinator.com'        => '{"result":"valid","reason":"accepted_email","disposable":"true","accept_all":"true","role":"false","free":"true","email":"abc@mailinator.com","user":"abc","domain":"mailinator.com","mx_record":"","mx_domain":"","safe_to_send":"false","did_you_mean":"","success":"true","message":""}',
            'test@iiron.us'             => '{"result":"valid","reason":"accepted_email","disposable":"true","accept_all":"true","role":"false","free":"false","email":"test@iiron.us","user":"test","domain":"iiron.us","mx_record":"","mx_domain":"","safe_to_send":"false","did_you_mean":"","success":"true","message":""}',
            'safe@example.com'          => '{"result":"valid","reason":"accepted_email","disposable":"false","accept_all":"false","role":"false","free":"false","email":"safe@example.com","user":"safe","domain":"example.com","mx_record":"","mx_domain":"","safe_to_send":"true","did_you_mean":"","success":"true","message":""}',
            'risky@example.com'         => '{"result":"unknown","reason":"accepted_email","disposable":"false","accept_all":"true","role":"false","free":"false","email":"risky@example.com","user":"risky","domain":"example.com","mx_record":"","mx_domain":"","safe_to_send":"false","did_you_mean":"","success":"true","message":""}',

        ];
    }

    public function testRiskAnalysis()
    {
        $validator = $this->getValidatorMock('safe@example.com');

        $this->assertSame(false, $validator->isHighRisk());

        $validator = $this->getValidatorMock('risky@example.com');

        $this->assertSame(true, $validator->isHighRisk());
    }

    public function testInvalidApiKey()
    {
        $validator = $this->getInvalidApiKeyMock('test@gmail.com', 401, '{"success": "false", "message": "Invalid API key", "code": 401}');

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
                            'https://api.quickemailverification.com/v1/verify',
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
        $provider = new \enricodias\EmailValidator\ServiceProviders\QuickEmailVerification('API_KEY');

        $client = $this->buildClientWithHistory($mock);

        return parent::buildValidator($client, $provider);
    }
}
