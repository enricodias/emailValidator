<?php

use enricodias\EmailValidator;
use PHPUnit\Framework\TestCase;

final class EmailValidatorTEst extends TestCase
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
