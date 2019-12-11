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

        $validator->removeProvider('validator.pizza');
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

    public function testDisposableList()
    {
        $validator = EmailValidator::create()->addDomains(['domain.com'])->validate('test@domain.com');

        $this->assertSame(true, $validator->isDisposable());
    }
    
    public function testDisposableListWildcard()
    {
        $validator = EmailValidator::create()->addDomains(['domain.*'])->validate('test@domain.com');

        $this->assertSame(true, $validator->isDisposable());

        $validator = EmailValidator::create()->addDomains(['*.domain.com'])->validate('test@sub.domain.com');

        $this->assertSame(true, $validator->isDisposable());
    }

    public function testAlias()
    {
        $validator = EmailValidator::create()->clearProviders()->validate('test+alias@gmail.com');

        $this->assertSame(true, $validator->isAlias());
    }

    public function testRequestsLeft()
    {
        $validator = EmailValidator::create()->clearProviders();

        $this->assertSame(-1, $validator->getRequestsLeft());
    }

    public function testGuzzleClient()
    {
        $validator = new EmailValidator();

        $this->assertSame(true, ($validator->getGuzzleClient() instanceof Client));
    }
}