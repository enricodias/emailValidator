# emailValidator

[![Build Status](https://img.shields.io/circleci/build/github/enricodias/emailValidator/master)](https://circleci.com/gh/enricodias/emailValidator/tree/master)
[![Codacy Badge](https://api.codacy.com/project/badge/Coverage/125d34db8a0443e0b433cbcde4786372)](https://www.codacy.com/manual/enricodias/emailValidator?utm_source=github.com&utm_medium=referral&utm_content=enricodias/emailValidator&utm_campaign=Badge_Coverage)
[![Codacy Badge](https://api.codacy.com/project/badge/Grade/125d34db8a0443e0b433cbcde4786372)](https://www.codacy.com/manual/enricodias/emailValidator?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=enricodias/emailValidator&amp;utm_campaign=Badge_Grade)
[![Latest version](http://img.shields.io/packagist/v/enricodias/email-validator.svg)](https://packagist.org/packages/enricodias/email-validator)
[![Downloads total](http://img.shields.io/packagist/dt/enricodias/email-validator.svg)](https://packagist.org/packages/enricodias/email-validator)
[![License](http://img.shields.io/packagist/l/enricodias/email-validator.svg)](https://github.com/enricodias/email-validator/blob/master/LICENSE.md)

Validate and check for disposable/temporary/throw away emails.

## Installation

Require this package with Composer in the root directory of your project.

```bash
composer require enricodias/email-validator
```

## Basic Usage

```php
$emailValidator = new \enricodias\EmailValidator\EmailValidator();
$emailValidator->validate('test+mail@gmail.co');

$emailValidator->isValid();      // false, gmail.co doesn't have valid MX entries
$emailValidator->isDisposable(); // false, gmail.co isn't a known domain for disposable emails
$emailValidator->isAlias();      // true, test+mail@gmail.co is alias for test@gmail.co
$emailValidator->didYouMean();   // test+mail@gmail.com
```

## Service Providers

A service provider is a third party service that validates the email, usually using an API. You may register several providers to be used on the validation.

The registered providers will be used in sequence until one of them returns a valid response. This is especially useful if you want a provider to act as a failover.

### Implemented providers

-   :pizza: [validator.pizza](https://www.validator.pizza/): a free API to check if domains are disposable. Enabled by default. Limited to 120 requests per hour per IP.

```php
$provider = new \enricodias\EmailValidator\ServiceProviders\ValidatorPizza();

$emailValidator->addProvider($provider, 'validator.pizza'); // the name is optional
```

### Custom providers

You can add a custom provider by implementing the class ```ServiceProviderInterface``` and register its instance using the ```addProvider()``` method. It's possible to remove the default validator.pizza provider using ```removeProvider()``` method or remove all all providers using ```clearProviders()``` method:

```php
$emailValidator = new \enricodias\EmailValidator\EmailValidator();

$emailValidator->clearProviders(); // remove all providers

$emailValidator->addProvider($CustomServiceProvider, 'My Custom Provider');

$emailValidator->validate('test@email.com');
```

You can use the static method ```create()``` to create an instance and chain methods:

```php
$emailValidator = \enricodias\EmailValidator\EmailValidator::create()
    ->removeProvider('validator.pizza'); // remove validator.pizza provider
    ->addProvider($CustomServiceProvider)
    ->validate('test@email.com');
```

Note that providers registered without a name cannot be removed by ```removeProvider()```.

## How it works

The class checks locally if the email syntax is valid and if so, it calls a service provider.

### Rate limit

Since most service providers are either paid or have a limit of requests per hour per ip, no request is made if the email doesn't pass on the local validation checks.

### Local domain list

To lower the number of API requests the local checks include a list with the most common disposable domains. This list is intended to be short in order to not affect performance and avoid the need of constants updates. Wildcards ```*``` are allowed.

### Additional Domains

It's likely that the most popular disposable email services among your users are not on the default list, so you may want to customize the list using the ```addDomains()``` method:

```php
$emailValidator = \enricodias\EmailValidator\EmailValidator::create()
    ->addDomains(['*.domain.com'])
    ->validate('test@sub.domain.com',);

$emailValidator->isDisposable(); // true
```

This method doesn't accepts a string, only an array.

## Validation methods

### isValid()

Returns ```true``` if the email is valid.

The email is considered invalid if it fails on the local syntax check OR if it fails in the service provider's check. Note that disposable emails are valid emails.

### isDisposable()

Returns ```true``` if the email is a disposable email.

### isAlias()

Returns ```true``` if the email is an alias. Example: ```test+mail@gmail.com``` is an alias of ```test@gmail.com```.

### didYouMean()

If the email has a simple and obvious typo such as ```gmail.cm``` instead of ```gmail.com``` this method will return a string with a suggested correction, otherwise it will return an empty string.

It's recommended to use this feature using ```javascript``` in the client side with an option for them to correct the email before submitting the form

## Client-side validation

Is possible to use a service provider's API on the client side. This is especially useful to provide the "didYouMean" feedback and allow the user to correct the email before submitting it. Here is a simple jQuery plugin that uses [validator.pizza](https://www.validator.pizza/): [enricodias/jQuery-Validator-Pizza](https://github.com/enricodias/jQuery-Validator-Pizza)
