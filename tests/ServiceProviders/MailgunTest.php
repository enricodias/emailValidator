<?php

namespace enricodias\EmailValidator\Tests\ServiceProviders;

use PHPUnit\Framework\TestCase;
use enricodias\EmailValidator\Tests\EmailTest;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

final class MailgunTest extends EmailTest implements ServiceProviderTestInterface
{
    public function getApiResponseList()
    {
        return [

            //email,                   apiResponse
            'abc'                       => '{"address": "abc", "is_disposable_address": false, "is_role_address": false, "reason": ["malformed address; missing @ sign"], "result": "undeliverable", "risk": "high"}',
            'gmail.com'                 => '{"address": "gmail.com", "is_disposable_address": false, "is_role_address": false, "reason": ["malformed address; missing @ sign"], "result": "undeliverable", "risk": "high"}',
            'john@gmail.com'            => '{"address": "john@gmail.com", "is_disposable_address": false, "is_role_address": false, "reason": [], "result": "deliverable", "risk": "low"}',
            'test@gmail+abc.com'        => '{"address": "test@gmail+abc.com", "is_disposable_address": false, "is_role_address": false, "reason": ["No MX records found for domain \'gmail+abc.com\'"], "result": "undeliverable", "risk": "high"}',
            
            // * Currently Mailgun doesn't return any suggestion for any email. This mock was edited manually to be a valid response according to their API doc
            'test@gmail.co'             => '{"address": "test@gmail.co", "did_you_mean": "test@gmail.com", "is_disposable_address": false, "is_role_address": false, "reason": [], "result": "deliverable", "risk": "high"}',
            
            'testvalid+alias@gmail.com' => '{"address": "testvalid+alias@gmail.com","is_disposable_address": false,"is_role_address": false,"reason": [],"result": "deliverable","risk": "low"}',
            'abc@mailinator.com'        => '{"address": "abc@mailinator.com", "is_disposable_address": true, "is_role_address": false, "reason": ["mailbox_is_disposable_address"], "result": "do_not_send", "risk": "high"}',
            'test@iiron.us'             => '{"address": "test@iiron.us", "is_disposable_address": true, "is_role_address": false, "reason": ["mailbox_is_disposable_address"], "result": "do_not_send", "risk": "high"}',
    
        ];
    }

    public function testGetResponse()
    {
        $email = 'john@gmail.com';

        $response = $this->getProviderResponseMock($email);

        $this->assertSame($response['address'], $email);
    }

    public function testInvalidApiKey()
    {
        $validator = $this->getInvalidApiKeyMock('test@gmail.com', 404, '{"message":"Invalid private key"}');

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
                            'https://api.mailgun.net/v4/address/validate',
                            [
                                'auth' => [
                                    'api:API_KEY',
                                ],
                                'query' => [
                                    'address' => 'test@domain.com',
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
        $provider = new \enricodias\EmailValidator\ServiceProviders\Mailgun('API_KEY');

        $client = new \GuzzleHttp\Client([
            'handler'  => \GuzzleHttp\HandlerStack::create($mock),
            'base_uri' => 'https://api.mailgun.net/v4/address/validate',
        ]);

        return parent::getMock($client, $provider);
    }
}