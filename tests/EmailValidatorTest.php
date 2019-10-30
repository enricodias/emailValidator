<?php

use enricodias\EmailValidator;
use PHPUnit\Framework\TestCase;

final class EmailValidatorTest extends TestCase
{
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
            ['gmail.com',            false,   false,        false,   ''],
            ['test@gmail.com',       true,    false,        false,   ''],
            ['test@gmail.co',        false,   false,        false,   'test@gmail.com'],
            ['test+alias@gmail.com', true,    false,        true,    ''],
            ['abc@mailinator.com',   true,    true,         false,   ''],

        ];
    }

}