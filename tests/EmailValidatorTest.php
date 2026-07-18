<?php

namespace enricodias\EmailValidator\Tests;

use PHPUnit\Framework\TestCase;
use enricodias\EmailValidator\EmailValidator;
use enricodias\EmailValidator\ServiceProviders\Mailgun;
use enricodias\EmailValidator\ServiceProviders\UserCheck;
use enricodias\EmailValidator\Tests\Utils\ArrayLogger;
use enricodias\EmailValidator\Tests\Utils\FakeServiceProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;

final class EmailValidatorTest extends TestCase
{
    public function testRemoveProviders()
    {
        $validator = new EmailValidator();

        $validator->removeProvider('UserCheck');
        $validator->removeProvider('NonExistentProvider');

        $validator->validate('test@mailinator.com');
        $this->assertSame(true, $validator->isDisposable());

        $validator->validate('test@gmail.co');
        $this->assertSame('', $validator->didYouMean());
    }

    public function testClearProviders()
    {
        $validator = EmailValidator::create()->clearProviders()->validate('test@mailinator.com');

        $this->assertSame(true, $validator->isDisposable());
    }

    // * We are not really testing randomness here, just that both providers can be picked
    public function testProviderWeight()
    {
        $iterations = 20;

        $mock = new MockHandler(
            \array_fill(0, $iterations, new Response(200, [], '{}'))
        );

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $requestFactory = new HttpFactory();

        $provider1 = new FakeServiceProvider();
        $provider2 = new FakeServiceProvider();

        $validator = new EmailValidator($client, $requestFactory);

        $validator->clearProviders()
            ->addProvider($provider1, '', 50, 1)
            ->addProvider($provider2, '', 50, 1);

        $usedFirstProvider  = false;
        $usedSecondProvider = false;

        for ($i = 0; $i < $iterations; $i++) {

            $validator->validate('test@iiron.us');

            if ($validator->getProvider() === $provider1) $usedFirstProvider = true;

            if ($validator->getProvider() === $provider2) $usedSecondProvider = true;

        }

        $this->assertTrue($usedFirstProvider, 'Provider1 was never picked across ' . $iterations . ' runs.');
        $this->assertTrue($usedSecondProvider, 'Provider2 was never picked across ' . $iterations . ' runs.');
    }

    public function testProviderPriority()
    {
        $mock = new MockHandler([
            new Response(200, [], '{}'),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $requestFactory = new HttpFactory();

        $lowPriorityProvider = new FakeServiceProvider();
        $highPriorityProvider = new FakeServiceProvider();

        $validator = new EmailValidator($client, $requestFactory);

        $validator->clearProviders()
            ->addProvider($highPriorityProvider, '', 1, 2)
            ->addProvider($lowPriorityProvider, '', 1, 1)
            ->validate('test@iiron.us');

        $this->assertSame($lowPriorityProvider, $validator->getProvider(), 'The provider with the lower priority value should be tried first.');
    }

    public function testAddProviderRejectsNegativeWeight()
    {
        $this->expectException(\InvalidArgumentException::class);

        EmailValidator::create()->addProvider(new FakeServiceProvider(), '', -1);
    }

    public function testAddProviderRejectsNegativePriority()
    {
        $this->expectException(\InvalidArgumentException::class);

        EmailValidator::create()->addProvider(new FakeServiceProvider(), '', 1, -1);
    }


    public function testDisposableList()
    {
        $validator = EmailValidator::create()->clearProviders()->addDomains(['domain.com'])->validate('test@domain.com');

        $this->assertSame(true, $validator->isDisposable());
    }

    public function testDisposableListWildcard()
    {
        $validator = EmailValidator::create()->clearProviders()->addDomains(['domain.*'])->validate('test@domain.com');

        $this->assertSame(true, $validator->isDisposable());

        $validator = EmailValidator::create()->clearProviders()->addDomains(['*.domain.com'])->validate('test@sub.domain.com');

        $this->assertSame(true, $validator->isDisposable());
    }

    public function testAlias()
    {
        $validator = EmailValidator::create()->clearProviders()->validate('test+alias@gmail.com');

        $this->assertTrue($validator->isAlias());

        $validator = EmailValidator::create()->clearProviders()->validate('test@gmail.com');

        $this->assertFalse($validator->isAlias());
    }

    public function testDisposableDoesNotLeakBetweenValidateCalls()
    {
        $validator = EmailValidator::create()->clearProviders()->validate('test@mailinator.com');

        $this->assertTrue($validator->isDisposable());

        $validator->clearProviders()->validate('test@gmail.com');

        $this->assertFalse($validator->isDisposable());
    }

    public function testHighRiskAndDidYouMeanDoNotLeakIntoLocalDisposableListResult()
    {
        $mock = new MockHandler([
            new Response(
                200,
                [],
                '{"address": "test@gmail.co", "did_you_mean": "test@gmail.com", "is_disposable_address": false, "is_role_address": false, "reason": [], "result": "deliverable", "risk": "high"}'
            ),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $requestFactory = new HttpFactory();

        $validator = new EmailValidator($client, $requestFactory);
        $validator->clearProviders()->addProvider(new Mailgun('API_KEY'));

        $validator->validate('test@gmail.co');

        $this->assertTrue($validator->isHighRisk());
        $this->assertSame('test@gmail.com', $validator->didYouMean());

        $validator->validate('test@mailinator.com');

        $this->assertTrue($validator->isDisposable());
        $this->assertFalse($validator->isHighRisk());
        $this->assertSame('', $validator->didYouMean());
    }

    public function testResultDoesNotLeakWhenEmailIsInvalid()
    {
        $validator = EmailValidator::create()->clearProviders()->validate('test@mailinator.com');

        $this->assertTrue($validator->isDisposable());

        $validator->validate('abc');

        $this->assertFalse($validator->isValid());
        $this->assertFalse($validator->isDisposable());
        $this->assertFalse($validator->isAlias());
        $this->assertSame('', $validator->didYouMean());
        $this->assertFalse($validator->isHighRisk());
    }

    public function testHighRiskDoesNotLeakBetweenDifferentProviders()
    {
        $mock = new \GuzzleHttp\Handler\MockHandler([
            new Response(
                200,
                [],
                '{"address": "test@iiron.us", "is_disposable_address": true, "is_role_address": false, "reason": [], "result": "do_not_send", "risk": "high"}'
            ),
            new Response(
                200,
                [],
                '{"status": 200, "domain": "gmail.com", "mx": true, "disposable": false, "alias": false, "did_you_mean": null, "remaining_requests": 100}'
            ),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $requestFactory = new HttpFactory();

        $validator = new EmailValidator($client, $requestFactory);

        $validator->clearProviders()->addProvider(new Mailgun('API_KEY'));
        $validator->validate('test@iiron.us');

        $this->assertTrue($validator->isHighRisk());

        $validator->clearProviders()->addProvider(new UserCheck());
        $validator->validate('test@gmail.com');

        $this->assertFalse($validator->isHighRisk());
    }

    public function testConstructorWithAutoDiscovery()
    {
        $validator = new EmailValidator();

        $this->assertInstanceOf(EmailValidator::class, $validator);
    }

    public function testConstructorWithExplicitDependencies()
    {
        $client = new Client();
        $requestFactory = new HttpFactory();

        $validator = new EmailValidator($client, $requestFactory);

        $this->assertInstanceOf(EmailValidator::class, $validator);
    }

    public function testConstructorWithLogger()
    {
        $logger = new ArrayLogger();

        $validator = new EmailValidator(null, null, $logger);

        $this->assertInstanceOf(EmailValidator::class, $validator);
    }

    public function testValidationIsLoggedForLocalDisposableList()
    {
        $logger = new ArrayLogger();

        $validator = new EmailValidator(null, null, $logger);
        $validator->validate('test@mailinator.com');

        $infoRecords = $logger->getRecordsByLevel('info');

        $this->assertNotEmpty($infoRecords);
        $this->assertSame('test@mailinator.com', $infoRecords[0]['context']['email']);
        $this->assertSame('local disposable domain list', $infoRecords[0]['context']['provider']);
        $this->assertTrue($infoRecords[0]['context']['disposable']);
    }

    public function testValidationIsLoggedWhenNoProvidersAreRegistered()
    {
        $logger = new ArrayLogger();

        $validator = new EmailValidator(null, null, $logger);
        $validator->clearProviders()->validate('test@gmail.com');

        $infoRecords = $logger->getRecordsByLevel('info');

        $this->assertNotEmpty($infoRecords);
        $this->assertSame('none', $infoRecords[0]['context']['provider']);
    }

    public function testAddProviderPropagatesLoggerAndRecordsValidation()
    {
        $logger = new ArrayLogger();

        $mock = new MockHandler([
            new Response(
                200,
                [],
                '{"status": 200, "domain": "iiron.us", "mx": true, "disposable": true, "alias": false, "did_you_mean": null, "remaining_requests": 100}'
            ),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $requestFactory = new HttpFactory();

        $validator = new EmailValidator($client, $requestFactory, $logger);
        $validator->clearProviders()->addProvider(new UserCheck(), 'MyProvider');

        $validator->validate('test@iiron.us');

        $debugRecords = $logger->getRecordsByLevel('debug');
        $infoRecords  = $logger->getRecordsByLevel('info');

        $this->assertNotEmpty($debugRecords, 'The logger set via addProvider() should be used by the provider.');
        $this->assertNotEmpty($infoRecords);
        $this->assertSame('MyProvider', $infoRecords[0]['context']['provider']);
        $this->assertSame('test@iiron.us', $infoRecords[0]['context']['email']);
        $this->assertTrue($infoRecords[0]['context']['disposable']);
    }

    public function testValidationLogsClassNameWhenProviderIsAddedWithoutAName()
    {
        $logger = new ArrayLogger();

        $mock = new MockHandler([
            new Response(200, [], '{}'),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $requestFactory = new HttpFactory();

        $validator = new EmailValidator($client, $requestFactory, $logger);
        $validator->clearProviders()->addProvider(new FakeServiceProvider());

        $validator->validate('test@domain.com');

        $infoRecords = $logger->getRecordsByLevel('info');

        $this->assertNotEmpty($infoRecords);
        $this->assertSame(FakeServiceProvider::class, $infoRecords[0]['context']['provider']);
    }
}
