<?php

namespace enricodias\EmailValidator\Tests;

use PHPUnit\Framework\TestCase;
use enricodias\EmailValidator\EmailValidator;
use enricodias\EmailValidator\Tests\Utils\ArrayLogger;
use enricodias\EmailValidator\Tests\Utils\FakeServiceProvider;

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

    // * We are not really test randomness here
    public function testShuffleProviders()
    {
        $provider1 = new \enricodias\EmailValidator\ServiceProviders\UserCheck();
        $provider2 = clone $provider1;

        $validator = EmailValidator::create()
            ->clearProviders()
            ->addProvider($provider1)
            ->addProvider($provider2)
            ->shuffleProviders()
            ->validate('test@iiron.us');

        $result = $validator->getProvider()->getResponse();

        $result1 = $provider1->getResponse();
        $result2 = $provider2->getResponse();

        $this->assertThat(
            $result,
            $this->logicalXor(
                $this->equalTo($result1),
                $this->equalTo($result2)
            )
        );
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

    public function testConstructorWithAutoDiscovery()
    {
        $validator = new EmailValidator();

        $this->assertInstanceOf(EmailValidator::class, $validator);
    }

    public function testConstructorWithExplicitDependencies()
    {
        $client = new \GuzzleHttp\Client();
        $requestFactory = new \GuzzleHttp\Psr7\HttpFactory();

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

        $mock = new \GuzzleHttp\Handler\MockHandler([
            new \GuzzleHttp\Psr7\Response(
                200,
                [],
                '{"status": 200, "domain": "iiron.us", "mx": true, "disposable": true, "alias": false, "did_you_mean": null, "remaining_requests": 100}'
            ),
        ]);

        $client = new \GuzzleHttp\Client(['handler' => \GuzzleHttp\HandlerStack::create($mock)]);
        $requestFactory = new \GuzzleHttp\Psr7\HttpFactory();

        $validator = new EmailValidator($client, $requestFactory, $logger);
        $validator->clearProviders()->addProvider(new \enricodias\EmailValidator\ServiceProviders\UserCheck(), 'MyProvider');

        $validator->validate('test@iiron.us');

        $debugRecords = $logger->getRecordsByLevel('debug');
        $infoRecords  = $logger->getRecordsByLevel('info');

        $this->assertNotEmpty($debugRecords, 'The logger set via addProvider() should be used by the provider.');
        $this->assertNotEmpty($infoRecords);
        $this->assertSame('myprovider', $infoRecords[0]['context']['provider']);
        $this->assertSame('test@iiron.us', $infoRecords[0]['context']['email']);
        $this->assertTrue($infoRecords[0]['context']['disposable']);
    }

    public function testValidationLogsClassNameWhenProviderIsAddedWithoutAName()
    {
        $logger = new ArrayLogger();

        $mock = new \GuzzleHttp\Handler\MockHandler([
            new \GuzzleHttp\Psr7\Response(200, [], '{}'),
        ]);

        $client = new \GuzzleHttp\Client(['handler' => \GuzzleHttp\HandlerStack::create($mock)]);
        $requestFactory = new \GuzzleHttp\Psr7\HttpFactory();

        $validator = new EmailValidator($client, $requestFactory, $logger);
        $validator->clearProviders()->addProvider(new FakeServiceProvider());

        $validator->validate('test@domain.com');

        $infoRecords = $logger->getRecordsByLevel('info');

        $this->assertNotEmpty($infoRecords);
        $this->assertSame(FakeServiceProvider::class, $infoRecords[0]['context']['provider']);
    }
}
