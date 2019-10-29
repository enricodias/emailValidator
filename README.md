# emailValidator

[![Build Status](https://img.shields.io/circleci/build/github/enricodias/emailValidator/master)](https://circleci.com/gh/enricodias/emailValidator/tree/master)
[![Codacy Badge](https://api.codacy.com/project/badge/Coverage/125d34db8a0443e0b433cbcde4786372)](https://www.codacy.com/manual/enricodias/emailValidator?utm_source=github.com&utm_medium=referral&utm_content=enricodias/emailValidator&utm_campaign=Badge_Coverage)
[![Codacy Badge](https://api.codacy.com/project/badge/Grade/125d34db8a0443e0b433cbcde4786372)](https://www.codacy.com/manual/enricodias/emailValidator?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=enricodias/emailValidator&amp;utm_campaign=Badge_Grade)
[![Latest version](http://img.shields.io/packagist/v/enricodias/email-validator.svg)](https://packagist.org/packages/enricodias/email-validator)
[![Downloads total](http://img.shields.io/packagist/dt/enricodias/email-validator.svg)](https://packagist.org/packages/enricodias/email-validator)
[![License](http://img.shields.io/packagist/l/enricodias/email-validator.svg)](https://github.com/enricodias/email-validator/blob/master/LICENSE.md)

A simple class to validate emails using <a href="https://validator.pizza">validator.pizza</a>, a free API to check if domains are disposable.

## Installation

Require this package with Composer in the root directory of your project.

```bash
composer require enricodias/email-validator
```

## Usage

```php

$emailValidator = \enricodias\EmailValidator('test+mail@gmail.co');

$emailValidator->isValid();     // false, gmail.co doesn't have valid MX entries
$emailValidator->isDisposable() // false, gmail.co isn't a known domain for disposable emails
$emailValidator->isAlias()      // true, test+mail@gmail.co is alias for test@gmail.co
$emailValidator->didYouMean()   // 'test+mail@gmail.com'
```

## How it works

### isValid()

The email is considered invalid if it fails on the ```respect/validation``` check OR if the email's domain doesn't have valid MX.

## Rate limit

Since <a href="https://validator.pizza">validator.pizza</a> limits how many request you can make per hour, no request is made if the email doesn't pass on the ```respect/validation``` validation. The function will also return ```true``` if validator.pizza returns a rate limit error.
