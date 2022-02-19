<?php

namespace enricodias\EmailValidator\Tests;

use PHPUnit\Framework\TestCase;
use enricodias\EmailValidator\EmailValidator;
use GuzzleHttp\Client;
final class EmailValidatorTest extends TestCase
{
    public function testRemoveProviders()
    {
        $validator = new EmailValidator();

        $validator->removeProvider('MailCheck.ai');
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
        $provider1 = new \enricodias\EmailValidator\ServiceProviders\MailCheckAi();
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

        $this->assertSame(true, $validator->isAlias());
    }

    public function testGuzzleClient()
    {
        $validator = new EmailValidator();

        $this->assertSame(true, ($validator->getGuzzleClient() instanceof Client));
    }
}
