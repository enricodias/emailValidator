<?php

namespace enricodias\EmailValidator\Tests\ServiceProviders;

use PHPUnit\Framework\TestCase;
use enricodias\EmailValidator\Tests\Utils\ArrayLogger;
use enricodias\EmailValidator\Tests\Utils\FakeServiceProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Tests the logging behaviour shared by every service provider through the abstract
 * ServiceProvider class, using a fake provider so these tests don't depend on any real provider.
 */
final class ServiceProviderTest extends TestCase
{
    private const API_KEY = 'API_KEY';

    public function testFailedRequestIsLogged()
    {
        $logger = new ArrayLogger();

        $provider = $this->buildProvider($logger);
        $client   = $this->buildClient(new MockHandler([
            new RequestException(
                'Error Communicating with Server',
                new Request('GET', 'https://api.example.com/verify')
            ),
        ]));

        $result = $provider->validate('test@domain.com', $client, new HttpFactory());

        $this->assertFalse($result);

        $debugRecords = $logger->getRecordsByLevel('debug');

        $this->assertNotEmpty($debugRecords);
        $this->assertStringContainsString('failed', $debugRecords[0]['message']);
        $this->assertFalse($logger->contains(self::API_KEY), 'The API key must never appear in the logs.');
    }

    public function testInvalidJsonResponseIsLogged()
    {
        $logger = new ArrayLogger();

        $provider = $this->buildProvider($logger);
        $client   = $this->buildClient(new MockHandler([
            new Response(200, [], 'this is not valid json'),
        ]));

        $result = $provider->validate('test@domain.com', $client, new HttpFactory());

        $this->assertFalse($result);

        $debugRecords = $logger->getRecordsByLevel('debug');

        $this->assertNotEmpty($debugRecords);
        $this->assertStringContainsString('invalid JSON', $debugRecords[0]['message']);
        $this->assertFalse($logger->contains(self::API_KEY), 'The API key must never appear in the logs.');
    }

    public function testSuccessfulRequestIsLoggedWithRedactedApiKey()
    {
        $logger = new ArrayLogger();

        $provider = $this->buildProvider($logger);
        $client   = $this->buildClient(new MockHandler([
            new Response(200, [], '{}'),
        ]));

        $result = $provider->validate('test@domain.com', $client, new HttpFactory());

        $this->assertTrue($result);

        $debugRecords = $logger->getRecordsByLevel('debug');

        $this->assertNotEmpty($debugRecords);
        $this->assertStringContainsString('succeeded', $debugRecords[0]['message']);

        // The API key is sent both in the query string and in the Authorization header,
        // covering both redaction paths in ServiceProvider::getLogContext().
        $this->assertFalse($logger->contains(self::API_KEY), 'The API key must never appear in the logs.');
        $this->assertSame('***REDACTED***', $debugRecords[0]['context']['headers']['Authorization']);
        $this->assertStringContainsString('***REDACTED***', $debugRecords[0]['context']['uri']);
    }

    public function testProviderWorksWithoutAnExplicitLogger()
    {
        $provider = new FakeServiceProvider(self::API_KEY);
        $client   = $this->buildClient(new MockHandler([
            new Response(200, [], '{}'),
        ]));

        $result = $provider->validate('test@domain.com', $client, new HttpFactory());

        $this->assertTrue($result, 'A provider must work normally without an explicit logger, using a NullLogger.');
    }

    private function buildProvider(ArrayLogger $logger): FakeServiceProvider
    {
        $provider = new FakeServiceProvider(self::API_KEY);

        $provider->setLogger($logger);

        return $provider;
    }

    private function buildClient(MockHandler $mock): Client
    {
        return new Client(['handler' => HandlerStack::create($mock)]);
    }
}
