<?php

namespace enricodias\EmailValidator\Tests\ServiceProviders;

use PHPUnit\Framework\TestCase;
use enricodias\EmailValidator\Tests\EmailTest;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

final class MailboxLayerTest extends EmailTest implements ServiceProviderTestInterface
{
    public function getApiResponseList()
    {
        return [

            //email,                   apiResponse
            'abc'                       => '{"email":"abc","did_you_mean":"","user":"abc","domain":null,"format_valid":false,"mx_found":null,"smtp_check":false,"catch_all":null,"role":false,"disposable":false,"free":false,"score":0.64}',
            'gmail.com'                 => '{"email":"gmail.com","did_you_mean":"","user":"gmail.com","domain":null,"format_valid":false,"mx_found":null,"smtp_check":false,"catch_all":null,"role":false,"disposable":false,"free":false,"score":0.64}',
            'john@gmail.com'            => '{"email":"john@gmail.com","did_you_mean":"","user":"john","domain":"gmail.com","format_valid":true,"mx_found":true,"smtp_check":false,"catch_all":null,"role":false,"disposable":false,"free":true,"score":0.64}',
            'test@gmail+abc.com'        => '{"email":"test@gmail+abc.com","did_you_mean":"","user":"test","domain":"gmail+abc.com","format_valid":false,"mx_found":null,"smtp_check":false,"catch_all":null,"role":false,"disposable":false,"free":false,"score":0.64}',
            'test@gmail.co'             => '{"email":"test@gmail.co","did_you_mean":"test@gmail.com","user":"test","domain":"gmail.co","format_valid":true,"mx_found":false,"smtp_check":false,"catch_all":null,"role":false,"disposable":false,"free":false,"score":0.32}',
            'testvalid+alias@gmail.com' => '{"email":"testvalid+alias@gmail.com","did_you_mean":"","user":"testvalid+alias","domain":"gmail.com","format_valid":true,"mx_found":true,"smtp_check":true,"catch_all":null,"role":false,"disposable":false,"free":true,"score":0.8}',
            'abc@mailinator.com'        => '{"email":"abc@mailinator.com","did_you_mean":"","user":"abc","domain":"mailinator.com","format_valid":true,"mx_found":true,"smtp_check":true,"catch_all":null,"role":false,"disposable":true,"free":false,"score":0.48}',
            'test@iiron.us'             => '{"email":"test@iiron.us","did_you_mean":"test@iiron.fr","user":"test","domain":"iiron.us","format_valid":true,"mx_found":true,"smtp_check":true,"catch_all":null,"role":false,"disposable":true,"free":false,"score":0.48}',
    
        ];
    }

    public function testRiskAnalysis()
    {
        $validator = $this->getValidatorMock('john@gmail.com');

        $this->assertSame(false, $validator->isHighRisk());

        $validator = $this->getValidatorMock('test@iiron.us');

        $this->assertSame(true, $validator->isHighRisk());
    }

    public function testGetResponse()
    {
        $email = 'john@gmail.com';

        $response = $validator = $this->getValidatorMock($email)->getProvider()->getResponse();

        $this->assertSame($email, $response['email']);
    }

    public function testInvalidApiKey()
    {
        $validator = $this->getInvalidApiKeyMock(
            'test@gmail.com',
            200,
            '{"success":false,"error":{"code":101,"type":"invalid_access_key","info":"You have not supplied a valid API Access Key. [Technical Support: support@apilayer.com]"}}'
        );

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
                            'https://apilayer.net/api/check',
                            [
                                'query' => [
                                    'email'  => 'test@domain.com',
                                    'access_key' => 'API_KEY'
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
        $provider = new \enricodias\EmailValidator\ServiceProviders\MailboxLayer('API_KEY');

        $client = new \GuzzleHttp\Client([
            'handler'  => \GuzzleHttp\HandlerStack::create($mock),
            'base_uri' => 'https://apilayer.net/api/check',
        ]);

        return parent::getMock($client, $provider);
    }
}