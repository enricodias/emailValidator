<?php

namespace enricodias\EmailValidator\Tests\ServiceProviders;

use enricodias\EmailValidator\EmailValidator;
use enricodias\EmailValidator\ServiceProviders\Clearout;
use enricodias\EmailValidator\Tests\EmailTest;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;

final class ClearoutTest extends EmailTest
{
    public function getApiResponseList()
    {
        return [

            //email,                   apiResponse
            'abc'                       => '{"status":"success","data":{"email_address":"abc","safe_to_send":"no","status":"invalid","disposable":"no","free":"no","role":"no","gibberish":"no","suggested_email_address":null,"bounce_type":"hard"}}',
            'gmail.com'                 => '{"status":"success","data":{"email_address":"gmail.com","safe_to_send":"no","status":"invalid","disposable":"no","free":"no","role":"no","gibberish":"no","suggested_email_address":null,"bounce_type":"hard"}}',
            'john@gmail.com'            => '{"status":"success","data":{"email_address":"john@gmail.com","safe_to_send":"yes","status":"valid","disposable":"no","free":"yes","role":"no","gibberish":"no","suggested_email_address":null,"bounce_type":null}}',
            'test@gmail+abc.com'        => '{"status":"success","data":{"email_address":"test@gmail+abc.com","safe_to_send":"no","status":"invalid","disposable":"no","free":"no","role":"no","gibberish":"no","suggested_email_address":null,"bounce_type":"hard"}}',
            'test@gmail.co'             => '{"status":"success","data":{"email_address":"test@gmail.co","safe_to_send":"yes","status":"valid","disposable":"no","free":"no","role":"no","gibberish":"no","suggested_email_address":"test@gmail.com","bounce_type":null}}',
            'testvalid+alias@gmail.com' => '{"status":"success","data":{"email_address":"testvalid+alias@gmail.com","safe_to_send":"yes","status":"valid","disposable":"no","free":"yes","role":"no","gibberish":"no","suggested_email_address":null,"bounce_type":null}}',
            'abc@mailinator.com'        => '{"status":"success","data":{"email_address":"abc@mailinator.com","safe_to_send":"yes","status":"valid","disposable":"yes","free":"no","role":"no","gibberish":"no","suggested_email_address":null,"bounce_type":null}}',
            'test@iiron.us'             => '{"status":"success","data":{"email_address":"test@iiron.us","safe_to_send":"yes","status":"valid","disposable":"yes","free":"no","role":"no","gibberish":"no","suggested_email_address":null,"bounce_type":null}}',

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
        $validator = $this->getInvalidApiKeyMock('test@gmail.com', 200, '{"status":"fail","message":"Invalid or expired token"}');

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
                            'POST',
                            'https://api.clearout.io/v2/email_verify/instant',
                            [
                                'Accept'        => 'application/json',
                                'Content-Type'  => 'application/json',
                                'Authorization' => 'Bearer API_KEY',
                            ],
                            \json_encode(['email' => 'test@domain.com'])
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
        $provider = new Clearout('API_KEY');

        $client = $this->buildClientWithHistory($mock);

        return parent::buildValidator($client, $provider);
    }

    /**
     * Clearout sends the email in the JSON request body rather than the URI, so the email is
     * looked up there instead of the query string.
     */
    protected function assertRequestContainsEmail(RequestInterface $request, string $email): void
    {
        $body = (string) $request->getBody();

        $this->assertStringContainsString($email, $body, 'The request sent to the service provider did not contain the expected email.');
    }
}
