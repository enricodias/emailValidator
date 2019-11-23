<?php

use PHPUnit\Framework\TestCase;
use enricodias\EmailValidator\EmailValidator;
use enricodias\EmailValidator\ServiceProviders;

final class EmailValidatorTest extends TestCase
{
    public function testDisposableList()
    {
        $validator = new EmailValidator('test@mailinator.com');
        $adapter   = new ServiceProviders\ValidatorPizza();

        // as mailinator.com is in the local domain list, no request should be made
        $this->assertSame(120, $validator->getRequestsLeft());

        $this->assertSame(true, $validator->isDisposable());
    }
    
    public function testDisposableListWillcard()
    {
        $validator = new EmailValidator('test@guerrillamail.com');

        $this->assertSame(true, $validator->isDisposable());

        $this->assertSame(120, $validator->getRequestsLeft());
    }

    public function testAdditionalDomains()
    {
        $validator = new EmailValidator('test@domain.com', ['domain.com']);

        // as domain.com is in the local domain list, no request should be made
        $this->assertSame(120, $validator->getRequestsLeft());

        $this->assertSame(true, $validator->isDisposable());
    }

    /**
     * @dataProvider emailsProvider
     */
    public function testEmails($email, $isValid, $isDisposable, $isAlias, $didYouMean)
    {
        $validator = new EmailValidator($email);
        
        $this->assertSame($isValid,      $validator->isValid());
        $this->assertSame($isDisposable, $validator->isDisposable());
        $this->assertSame($isAlias,      $validator->isAlias());
        $this->assertSame($didYouMean,   $validator->didYouMean());
    }

    /**
     * List of emails to be tested.
     * 
     * Note that validator.pizza limits the requests to 120 per hour, per IP address.
     * This package is testes in 3 PHP versions on CircleCI, 
     * therfore the tests must not exceed 40 requests on each build.
     * 
     * @codeCoverageIgnore
     */
    public function emailsProvider()
    {
        return [
            
            //email,                 isValid, isDisposable, isAlias, didYouMean
            ['abc',                  false,   false,        false,   ''],
            ['gmail.com',            false,   false,        false,   ''],
            ['test@gmail.com',       true,    false,        false,   ''],
            ['test@gmail+abc.com',   false,   false,        false,   ''],
            ['test@gmail.co',        true,   false,        false,   'test@gmail.com'],
            ['test+alias@gmail.com', true,    false,        true,    ''],
            ['abc@mailinator.com',   true,    true,         false,   ''],

        ];
    }
}