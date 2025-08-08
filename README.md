# emailValidator

[![Build Status](https://img.shields.io/circleci/build/github/enricodias/emailValidator/master)](https://circleci.com/gh/enricodias/emailValidator/tree/master)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/125d34db8a0443e0b433cbcde4786372)](https://app.codacy.com/gh/enricodias/emailValidator/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)
[![Codacy Badge](https://app.codacy.com/project/badge/Coverage/125d34db8a0443e0b433cbcde4786372)](https://app.codacy.com/gh/enricodias/emailValidator/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_coverage)
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

UserCheck is enabled by default.

```php
$MailboxLayer = new \enricodias\EmailValidator\ServiceProviders\MailboxLayer('API_KEY');

$Mailgun = new \enricodias\EmailValidator\ServiceProviders\Mailgun('API_KEY');

$emailValidator->addProvider($MailboxLayer, 'MailboxLayer');
$emailValidator->addProvider($Mailgun); // the name is optional

$emailValidator->validate('test@email.com');
```

### Implemented providers

| Provider                                  | Free Tier                    | Cost per validation  | Unsupported Features |
|-------------------------------------------|------------------------------|----------------------|----------------------|
| [UserCheck](https://www.usercheck.com/)   | 1000 verifications per month | $0.00014 to $0.00025 | ```isHighRisk()```   |
| [MailboxLayer](https://mailboxLayer.com/) | 250 verifications per month  | $0.002 to $0.0006    |                      |
| [NeverBounce](https://neverbounce.com/)   | 1000 verifications           | $0.008 to $0.003     | ```isHighRisk()```   |
| [Kickbox](https://kickbox.com/)           | 100 verifications            | $0.010 to $0.004     |                      |
| [Mailgun](https://mailgun.com/)           | 0                            | $0.012 to $0.0025    | ```didYouMean()```** |

\* MailCheck.ai and Validator.pizza is now called UserCheck
\*\* the feature is documented but as for now, the API never returns a suggestion.

### Custom providers

You can add a custom provider by implementing the class ```ServiceProviderInterface```. It's possible to remove the default UserCheck provider using ```removeProvider()``` method or remove all all providers using ```clearProviders()``` method:

```php
$emailValidator = new \enricodias\EmailValidator\EmailValidator();

$emailValidator->clearProviders(); // remove all providers

$emailValidator->addProvider($CustomServiceProvider, 'My Custom Provider');

$emailValidator->validate('test@email.com');
```

You can use the static method ```create()``` to create an instance and chain methods:

```php
$emailValidator = \enricodias\EmailValidator\EmailValidator::create()
    ->removeProvider('UserCheck');
    ->addProvider($CustomServiceProvider)
    ->validate('test@email.com');
```

Note that providers registered without a name cannot be removed by ```removeProvider()```.

### Shuffle providers

Shuffling the service providers list is useful when using the free tier of multiple providers. Without shuffling, the providers will always be used in the same order resulting in unnecessary failures when the first provider runs out of credits.

```php
$emailValidator->clearProviders()
    ->addProvider($Provider1)
    ->addProvider($Provider2)
    ->shuffleProviders()
    ->validate('test@email.com');
```

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

### isHighRisk()

Most service providers have a risk analysis tool. This method returns ```true``` if the risk is high.
