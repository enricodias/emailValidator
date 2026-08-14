<?php

namespace enricodias\EmailValidator\Tests;

use enricodias\EmailValidator\EmailValidator;
use enricodias\EmailValidator\ServiceProviders\AbstractApi;
use enricodias\EmailValidator\ServiceProviders\MailboxLayer;
use enricodias\EmailValidator\ServiceProviders\QuickEmailVerification;
use enricodias\EmailValidator\ServiceProviders\QuotaAwareServiceProviderInterface;
use enricodias\EmailValidator\ServiceProviders\UserCheck;
use enricodias\EmailValidator\ServiceProviders\ZeroBounce;
use enricodias\EmailValidator\Tests\Utils\ArrayCacheItemPool;
use enricodias\EmailValidator\Tests\Utils\ArrayLogger;
use enricodias\EmailValidator\Tests\Utils\FakeServiceProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class ProviderCooldownTest extends TestCase
{
    public function testQuickEmailVerificationCooldownSkipsTheProviderAndUsesFallback(): void
    {
        $cache = new ArrayCacheItemPool();
        $logger = new ArrayLogger();
        $mock = new MockHandler([
            new Response(402, [], '{"success":"false","message":"Credit limit reached"}'),
            new Response(200, [], '{}'),
            new Response(200, [], '{}'),
        ]);

        $validator = $this->buildValidator($mock, $cache, $logger);

        $validator->clearProviders()
            ->addProvider(new QuickEmailVerification('quick-key'), 'quick', 1, 1)
            ->addProvider(new FakeServiceProvider(), 'fallback', 1, 2);

        $validator->validate('first@example.com');

        $this->assertTrue($validator->isValid());
        $this->assertInstanceOf(FakeServiceProvider::class, $validator->getProvider());

        $warningRecords = $logger->getRecordsByLevel('warning');

        $this->assertNotEmpty($warningRecords);
        $this->assertSame('quick', $warningRecords[0]['context']['provider']);

        $validator->validate('second@example.com');

        $this->assertTrue($validator->isValid());
        $this->assertInstanceOf(FakeServiceProvider::class, $validator->getProvider());

        $infoRecords = $logger->getRecordsByLevel('info');
        $skippedRecords = \array_values(\array_filter($infoRecords, static function (array $record) {

            return $record['context']['provider'] === 'quick';

        }));

        $this->assertNotEmpty($skippedRecords);
    }

    public function testCooldownIsScopedToProviderAndApiKey(): void
    {
        $cache = new ArrayCacheItemPool();
        $firstLogger = new ArrayLogger();
        $first = $this->buildValidator(new MockHandler([
            new Response(402, [], '{"success":"false","message":"Credit limit reached"}'),
        ]), $cache, $firstLogger);
        $first->clearProviders()->addProvider(new QuickEmailVerification('old-key'));

        $first->validate('first@example.com');

        $this->assertTrue($first->isValid());

        $firstProvider = $first->getProvider();

        $this->assertInstanceOf(QuotaAwareServiceProviderInterface::class, $firstProvider);
        $this->assertInstanceOf(\DateTimeImmutable::class, $firstProvider->getQuotaCooldownUntil());

        $warningRecords = $firstLogger->getRecordsByLevel('warning');

        $this->assertNotEmpty($warningRecords);
        $this->assertSame(QuickEmailVerification::class, $warningRecords[0]['context']['provider']);

        $second = $this->buildValidator(new MockHandler([
            new Response(200, [], '{"result":"valid","disposable":"false","safe_to_send":"true","did_you_mean":"","success":"true"}'),
        ]), $cache);
        $second->clearProviders()->addProvider(new QuickEmailVerification('replacement-key'));

        $second->validate('second@example.com');

        $this->assertTrue($second->isValid());
        $this->assertInstanceOf(QuickEmailVerification::class, $second->getProvider());
    }

    public function testResultIsNotCachedWhenNoProviderCouldValidate(): void
    {
        $cache = new ArrayCacheItemPool();
        $validator = $this->buildValidator(new MockHandler([
            new RequestException(
                'Error Communicating with Server',
                new Request('GET', 'https://api.example.com/verify')
            ),
        ]), $cache);
        $validator->clearProviders()->addProvider(new FakeServiceProvider());

        $validator->validate('first@example.com');

        $this->assertTrue($validator->isValid());
        $this->assertSame(0, $cache->count());
    }

    public function testMailboxLayerOnlyCachesTheDocumentedMonthlyLimit(): void
    {
        $provider = new MailboxLayer('mailbox-key');

        $provider->validate('test@example.com', $this->client(new MockHandler([
            new Response(429, [], '{"message":"You have exceeded your daily API rate limit."}'),
        ])), new HttpFactory());

        $this->assertNull($provider->getQuotaCooldownUntil());

        $provider->validate('test@example.com', $this->client(new MockHandler([
            new Response(429, [], '{"message":"You have exceeded your daily/monthly API rate limit."}'),
        ])), new HttpFactory());

        $this->assertInstanceOf(\DateTimeImmutable::class, $provider->getQuotaCooldownUntil());
    }

    public function testAbstractAndZeroBounceDetectTheirQuotaResponses(): void
    {
        $abstract = new AbstractApi('abstract-key');

        $abstract->validate('test@example.com', $this->client(new MockHandler([
            new Response(422, [], '{"error":{"message":"Quota reached"}}'),
        ])), new HttpFactory());

        $this->assertInstanceOf(\DateTimeImmutable::class, $abstract->getQuotaCooldownUntil());

        $zeroBounce = new ZeroBounce('zero-key');

        $zeroBounce->validate('test@example.com', $this->client(new MockHandler([
            new Response(200, [], '{"error":"Invalid API Key or your account ran out of credits"}'),
        ])), new HttpFactory());

        $this->assertInstanceOf(\DateTimeImmutable::class, $zeroBounce->getQuotaCooldownUntil());
    }

    public function testUserCheckCachesOnlyConfirmedZeroCreditQuota(): void
    {
        $provider = new UserCheck('usercheck-key');

        $provider->validate('test@example.com', $this->client(new MockHandler([
            new Response(429, [], '{"status":429,"error":"Too many requests"}'),
            new Response(200, [], '{"status":"success","usage":{"remaining":0,"reset_at":"2030-01-01T00:00:00.000000Z"}}'),
        ])), new HttpFactory());

        $this->assertSame('2030-01-01T00:00:00+00:00', $provider->getQuotaCooldownUntil()->format(\DateTimeInterface::ATOM));
    }

    private function buildValidator(MockHandler $mock, ArrayCacheItemPool $cache, ?ArrayLogger $logger = null): EmailValidator
    {
        return new EmailValidator($this->client($mock), new HttpFactory(), $cache, $logger);
    }

    private function client(MockHandler $mock): Client
    {
        return new Client(['handler' => HandlerStack::create($mock)]);
    }
}
