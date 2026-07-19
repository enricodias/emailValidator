<?php

namespace enricodias\EmailValidator\Tests;

use PHPUnit\Framework\TestCase;
use enricodias\EmailValidator\EmailValidator;
use enricodias\EmailValidator\ServiceProviders\Mailgun;
use enricodias\EmailValidator\ServiceProviders\UserCheck;
use enricodias\EmailValidator\Tests\Utils\ArrayCacheItemPool;
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

        $validator->addDisposableDomains(['mailinator.com']);
        $validator->removeProvider('UserCheck');
        $validator->removeProvider('NonExistentProvider');

        $validator->validate('test@mailinator.com');
        $this->assertSame(true, $validator->isDisposable());

        $this->expectException(\LogicException::class);

        $validator->validate('test@gmail.co');
    }

    public function testClearProviders()
    {
        $validator = EmailValidator::create()->clearProviders()->addDisposableDomains(['mailinator.com'])->validate('test@mailinator.com');

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
        $validator = EmailValidator::create()->clearProviders()->addDisposableDomains(['domain.com'])->validate('test@domain.com');

        $this->assertSame(true, $validator->isDisposable());
    }

    public function testDisposableListWildcard()
    {
        $validator = EmailValidator::create()->clearProviders()->addDisposableDomains(['domain.*'])->validate('test@domain.com');

        $this->assertSame(true, $validator->isDisposable());

        $validator = EmailValidator::create()->clearProviders()->addDisposableDomains(['*.domain.com'])->validate('test@sub.domain.com');

        $this->assertSame(true, $validator->isDisposable());
    }

    public function testAlias()
    {
        $mock = new MockHandler([
            new Response(200, [], '{}'),
            new Response(200, [], '{}'),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $requestFactory = new HttpFactory();

        $validator = EmailValidator::create($client, $requestFactory)->clearProviders()->addProvider(new FakeServiceProvider());

        $validator->validate('test+alias@gmail.com');
        $this->assertTrue($validator->isAlias());

        $validator->validate('test@gmail.com');
        $this->assertFalse($validator->isAlias());
    }

    public function testDisposableDoesNotLeakBetweenValidateCalls()
    {
        $mock = new MockHandler([
            new Response(200, [], '{}'),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $requestFactory = new HttpFactory();

        $validator = EmailValidator::create($client, $requestFactory)->clearProviders()->addDisposableDomains(['mailinator.com'])->validate('test@mailinator.com');

        $this->assertTrue($validator->isDisposable());

        $validator->clearProviders()->addProvider(new FakeServiceProvider())->validate('test@gmail.com');

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
        $validator->clearProviders()->addProvider(new Mailgun('API_KEY'))->addDisposableDomains(['mailinator.com']);

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
        $validator = EmailValidator::create()->clearProviders()->addDisposableDomains(['mailinator.com'])->validate('test@mailinator.com');

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

        $validator = new EmailValidator(null, null, null, $logger);

        $this->assertInstanceOf(EmailValidator::class, $validator);
    }

    public function testValidationIsLoggedForLocalDisposableList()
    {
        $logger = new ArrayLogger();

        $validator = new EmailValidator(null, null, null, $logger);
        $validator->addDisposableDomains(['mailinator.com']);
        $validator->validate('test@mailinator.com');

        $infoRecords = $logger->getRecordsByLevel('info');

        $this->assertNotEmpty($infoRecords);
        $this->assertSame('test@mailinator.com', $infoRecords[0]['context']['email']);
        $this->assertSame('local disposable domain list', $infoRecords[0]['context']['provider']);
        $this->assertTrue($infoRecords[0]['context']['disposable']);
    }

    public function testValidationThrowsWhenNoProvidersAreRegistered()
    {
        $validator = EmailValidator::create()->clearProviders();

        $this->expectException(\LogicException::class);

        $validator->validate('test@gmail.com');
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

        $validator = new EmailValidator($client, $requestFactory, null, $logger);
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

        $validator = new EmailValidator($client, $requestFactory, null, $logger);
        $validator->clearProviders()->addProvider(new FakeServiceProvider());

        $validator->validate('test@domain.com');

        $infoRecords = $logger->getRecordsByLevel('info');

        $this->assertNotEmpty($infoRecords);
        $this->assertSame(FakeServiceProvider::class, $infoRecords[0]['context']['provider']);
    }

    public function testConstructorWithCache()
    {
        $cache = new ArrayCacheItemPool();

        $validator = new EmailValidator(null, null, $cache);

        $this->assertInstanceOf(EmailValidator::class, $validator);
    }


    public function testProviderResultIsCachedAndReusedOnSecondValidateCall()
    {
        $cache = new ArrayCacheItemPool();

        $mock = new MockHandler([
            new Response(
                200,
                [],
                '{"status": 200, "domain": "iiron.us", "mx": true, "disposable": false, "alias": false, "did_you_mean": null, "remaining_requests": 100}'
            ),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $requestFactory = new HttpFactory();

        $validator = new EmailValidator($client, $requestFactory, $cache);

        $validator->validate('test@iiron.us');

        $this->assertTrue($validator->isValid());
        $this->assertFalse($validator->isDisposable());

        // Only one response is queued in the mock handler, so a second real API call would throw.
        // Reaching these assertions proves the second validate() call was served from the cache.
        $validator->validate('test@iiron.us');

        $this->assertTrue($validator->isValid());
        $this->assertFalse($validator->isDisposable());
    }

    public function testCacheKeyIsCaseInsensitive()
    {
        $cache = new ArrayCacheItemPool();

        $mock = new MockHandler([
            new Response(
                200,
                [],
                '{"status": 200, "domain": "iiron.us", "mx": true, "disposable": false, "alias": false, "did_you_mean": null, "remaining_requests": 100}'
            ),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $requestFactory = new HttpFactory();

        $validator = new EmailValidator($client, $requestFactory, $cache);

        $validator->validate('Test@IIRON.US');
        $validator->validate('test@iiron.us');

        $this->assertTrue($validator->isValid());
    }

    public function testLocalDisposableListResultIsNotCached()
    {
        $cache = new ArrayCacheItemPool();
        $logger = new ArrayLogger();

        $validator = new EmailValidator(null, null, $cache, $logger);

        $validator->addDisposableDomains(['mailinator.com']);
        $validator->validate('test@mailinator.com');
        $validator->validate('test@mailinator.com');

        $infoRecords = $logger->getRecordsByLevel('info');

        $this->assertSame('local disposable domain list', $infoRecords[0]['context']['provider']);
        $this->assertSame('local disposable domain list', $infoRecords[1]['context']['provider']);
        $this->assertTrue($validator->isDisposable());
        $this->assertTrue($validator->isValid());
    }

    public function testCacheHitRestoresFullResultIncludingHighRisk()
    {
        $cache = new ArrayCacheItemPool();

        $mock = new MockHandler([
            new Response(
                200,
                [],
                '{"status": 200, "domain": "iiron.us", "mx": true, "disposable": true, "alias": false, "did_you_mean": "gmail.com", "remaining_requests": 100}'
            ),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $requestFactory = new HttpFactory();

        $validator = new EmailValidator($client, $requestFactory, $cache);

        $validator->validate('test@iiron.us');
        $validator->validate('test@iiron.us');

        $this->assertTrue($validator->isDisposable());
        $this->assertSame('test@gmail.com', $validator->didYouMean());
    }
}
