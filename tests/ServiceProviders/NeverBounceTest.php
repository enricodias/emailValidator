<?php

namespace enricodias\EmailValidator\Tests\ServiceProviders;

use PHPUnit\Framework\TestCase;
use enricodias\EmailValidator\Tests\EmailTest;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

final class NeverBounceTest extends EmailTest implements ServiceProviderTestInterface
{
    public function getApiResponseList()
    {
        return [

            //email,                   apiResponse
            'abc'                       => '{"status":"success","result":"invalid","flags":["bad_syntax"],"suggested_correction":"","execution_time":39}',
            'gmail.com'                 => '{"status":"success","result":"invalid","flags":["bad_syntax"],"suggested_correction":"","execution_time":34}',
            'john@gmail.com'            => '{"status":"success","result":"valid","flags":["free_email_host","has_dns","has_dns_mx"],"suggested_correction":"","execution_time":217}',
            'test@gmail+abc.com'        => '{"status":"success","result":"invalid","flags":["bad_syntax"],"suggested_correction":"","execution_time":30}',
            'test@gmail.co'             => '{"status":"success","result":"valid","flags":["free_email_host","role_account","spelling_mistake","has_dns"],"suggested_correction":"test@gmail.com","execution_time":219}',
            
            // this response is for actually testvalid@gmail.com since NeverBounce doesn't support aliases
            'testvalid+alias@gmail.com' => '{"status":"success","result":"valid","flags":["free_email_host","has_dns","has_dns_mx","smtp_connectable"],"suggested_correction":"","execution_time":607}',
            
            'abc@mailinator.com'        => '{"status":"success","result":"disposable","flags":["role_account","disposable_email"],"suggested_correction":"","execution_time":30}',
            'test@iiron.us'             => '{"status":"success","result":"disposable","flags":["role_account","has_dns","has_dns_mx","smtp_connectable","accepts_all"],"suggested_correction":"","execution_time":819}',
    
        ];
    }

    public function testInvalidApiKey()
    {
        $validator = $this->getServiceMock(
            new MockHandler(
                [
                    new Response(
                        404,
                        [],
                        '{"status":"auth_failure","message":"Invalid API key \'API_KEY\'","execution_time":338}'
                    ),
                ]
            )
        );

        $validator->validate('test@gmail.com');

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
                            'https://api.neverbounce.com/v4/single/check',
                            [
                                'query' => [
                                    'key'   => 'API_KEY',
                                    'email' => 'test@domain.com',
                                ]
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
        $provider = new \enricodias\EmailValidator\ServiceProviders\NeverBounce('API_KEY');

        $client = new \GuzzleHttp\Client([
            'handler'  => \GuzzleHttp\HandlerStack::create($mock),
            'base_uri' => 'https://api.neverbounce.com/v4/single/check',
        ]);

        return parent::getMock($client, $provider);
    }
}