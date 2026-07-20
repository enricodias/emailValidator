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

This package communicates with service providers using [PSR-18](https://www.php-fig.org/psr/psr-18/) (HTTP Client) and [PSR-17](https://www.php-fig.org/psr/psr-17/) (HTTP Factories). It doesn't bundle an HTTP client implementation, so your project needs one installed. [Guzzle](https://github.com/guzzle/guzzle) is a good default:

```bash
composer require guzzlehttp/guzzle
```

If you don't explicitly provide a client and a request factory (see [HTTP Client and Request Factory](#http-client-and-request-factory) below), one will be auto-discovered from the packages installed in your project using [php-http/discovery](https://github.com/php-http/discovery).

## Basic Usage

```php
use \enricodias\EmailValidator\EmailValidator;

$emailValidator = new EmailValidator();
$emailValidator->validate('test+mail@gmail.co');

$emailValidator->isValid();      // false, gmail.co doesn't have valid MX entries
$emailValidator->isDisposable(); // false, gmail.co isn't a known domain for disposable emails
$emailValidator->isAlias();      // true, test+mail@gmail.co is alias for test@gmail.co
$emailValidator->didYouMean();   // test+mail@gmail.com
```

### HTTP Client and Request Factory

By default, `EmailValidator` auto-discovers a PSR-18 HTTP client and a PSR-17 request factory from the packages installed in your project. If your framework already provides these as services (e.g. Symfony or Laravel autowiring), you can inject them explicitly through the constructor instead:

```php
$emailValidator = new EmailValidator($httpClient, $requestFactory);
```

`$httpClient` must implement `Psr\Http\Client\ClientInterface` and `$requestFactory` must implement `Psr\Http\Message\RequestFactoryInterface`. Both parameters are optional and independent of each other, so you can provide just one of them and let the other be auto-discovered.

## Service Providers

A service provider is a third party service that validates the email, usually using an API. You may register several providers to be used on the validation.

UserCheck is enabled by default and works without an API key, but you can provide one to use higher rate limits.

```php
use \enricodias\EmailValidator\ServiceProviders\MailboxLayer;
use \enricodias\EmailValidator\ServiceProviders\Mailgun;

$MailboxLayer = new MailboxLayer('API_KEY');
$Mailgun = new Mailgun('API_KEY');

$emailValidator->addProvider($MailboxLayer, 'MailboxLayer');
$emailValidator->addProvider($Mailgun); // the name is optional

$emailValidator->validate('test@email.com');
```

### Implemented providers

| Provider | Free Tier | Cost per validation | Unsupported Features |
|---|---|---|---|
| [QuickEmailVerification](https://quickemailverification.com) | 3000 verifications per month | $0.008 to $0.0007 | |
| [UserCheck](https://www.usercheck.com/) | 1000 verifications per month | $0.00014 to $0.00025 | `isHighRisk()` |
| [MailboxLayer](https://mailboxLayer.com/) | 250 verifications per month | $0.002 to $0.0006 | |
| [NeverBounce](https://neverbounce.com/) | 1000 verifications | $0.008 to $0.003 | |
| [Kickbox](https://kickbox.com/) | 100 verifications | $0.010 to $0.004 | |
| [Mailgun](https://mailgun.com/) | 0 | $0.012 to $0.0025 | `didYouMean()`** |
| [ZeroBounce](https://www.zerobounce.net/) | 100 verifications per month | $0.0195 to $0.0032 | |
| [Clearout](https://clearout.io/) | 100 verifications | $0.008 to $0.001 | |

\* MailCheck.ai and Validator.pizza is now called UserCheck
\*\* the feature is documented but as for now, the API never returns a suggestion.

### Custom providers

You can add a custom provider by implementing the class `ServiceProviderInterface`. It's possible to remove the default UserCheck provider using `removeProvider()` method or remove all all providers using ```clearProviders()``` method:

```php
$emailValidator = new EmailValidator();

$emailValidator->clearProviders(); // remove all providers

$emailValidator->addProvider($CustomServiceProvider, 'My Custom Provider');

$emailValidator->validate('test@email.com');
```

You can use the static method `create()` to create an instance and chain methods:

```php
$emailValidator = EmailValidator::create()
    ->removeProvider('UserCheck')
    ->addProvider($CustomServiceProvider)
    ->validate('test@email.com');
```

`create()` also accepts the same optional `$httpClient`, `$requestFactory`, `$logger` and `$cache` parameters as the constructor:

```php
$emailValidator = EmailValidator::create($httpClient, $requestFactory, $logger, $cache)
    ->addProvider($CustomServiceProvider)
    ->validate('test@email.com');
```

Note that providers registered without a name cannot be removed by `removeProvider()`.

### Weight and priority

`addProvider()` accepts an optional weight and priority, useful for balancing the free tier of multiple providers and/or using a paid service as a fallback.

Providers are grouped by priority and tried starting from the lowest value. Within the same priority, the provider used is picked randomly on every `validate()` call, weighted by `$weight` so a higher weight means a higher chance of being tried first, without ever being guaranteed.

```php
$emailValidator->clearProviders()
    ->addProvider($QuickEmailVerification, 'QuickEmailVerification', 75, 1)
    ->addProvider($UserCheck, 'UserCheck', 25, 1)
    ->addProvider($Mailgun, 'Mailgun', 1, 2)
    ->validate('test@email.com');
```

In the example above, `QuickEmailVerification` has a 75% chance of being tried first and `UserCheck` a 25% chance, allowing to use their free tiers evenly. `Mailgun` is not tried unless both `QuickEmailVerification` and `UserCheck` fail, since they're registered with a higher priority value.

Both `$weight` and `$priority` default to `1` and must not be negative. A `$weight` of `0` means the provider is only tried once every other provider in its priority group has been tried and failed. A fixed sequence of providers can still be forced by giving each one its own increasing priority.

### Logging

`EmailValidator` accepts an optional [PSR-3](https://www.php-fig.org/psr/psr-3/) logger as the third constructor parameter. [Monolog](https://github.com/Seldaek/monolog) is recommended.

Install it with composer:

```bash
composer require monolog/monolog
```

Inject it as the fourth constructor parameter:

```php
use \Monolog\Handler\StreamHandler;
use \Monolog\Logger;

$logger = new Logger('email-validator');
$logger->pushHandler(new StreamHandler('path/to/your.log'));

$emailValidator = new EmailValidator(null, null, null, $logger);
```

The logger records:

- An `info` entry every time `validate()` is called, with the email, the service provider used (or how the result was determined, e.g. the local disposable domain list) and the outcome.
- A `debug` entry whenever a service provider request fails, returns an invalid response, or succeeds, which is useful for diagnosing integration issues with a specific provider.

API keys are always redacted from log messages.

Any provider that implements `Psr\Log\LoggerAwareInterface` (the built-in `ServiceProvider` base class already does) automatically receives the same logger when registered with `addProvider()`, so custom providers get this behaviour for free by extending it.

### Caching

`EmailValidator` accepts an optional [PSR-6](https://www.php-fig.org/psr/psr-6/) cache pool as the third constructor parameter. When provided, the result of `validate()` is stored in the cache and reused on subsequent calls for the same email, avoiding a duplicate service provider request.

Note that `psr/cache` versions 2.0 and 3.0 require PHP 8.0+, so this library depends on `psr/cache` `^1.0` to keep PHP 7.3 support. Make sure the cache implementation you install is compatible with `psr/cache` `^1.0`. [php-cache/filesystem-adapter](https://github.com/php-cache/filesystem-adapter) is recommended.

Install it with composer:

```bash
composer require cache/filesystem-adapter
```

Inject it as the fourth constructor parameter:

```php
use \League\Flysystem\Filesystem;
use \League\Flysystem\Local\LocalFilesystemAdapter;
use \Cache\Adapter\Filesystem\FilesystemCachePool;

$filesystem = new Filesystem(new LocalFilesystemAdapter('path/to/cache'));
$cache = new FilesystemCachePool($filesystem);

$emailValidator = new EmailValidator(null, null, null, $cache);
```

Caching is disabled by default. Emails are matched to a cache entry regardless of case, and the cached result is only used when the email syntax is valid, so invalid emails are always checked locally on every call.

## How it works

The class checks locally if the email syntax is valid and if so, it calls a service provider.

### Rate limit

Since most service providers are either paid or have a limit of requests per hour per ip, no request is made if the email doesn't pass on the local validation checks.

### Local disposable domains list

To lower the number of API requests, you can setup a list of disposable domains to check locally using the `addDisposableDomains()` method:

```php
$emailValidator = EmailValidator::create()
    ->addDisposableDomains(['*.domain.com'])
    ->validate('test@sub.domain.com',);

$emailValidator->isDisposable(); // true
```

Wildcards `*` are allowed.

## Validation methods

### isValid()

Returns `true` if the email is valid.

The email is considered invalid if it fails on the local syntax check OR if it fails in the service provider's check. Note that disposable emails are valid emails.

### isDisposable()

Returns `true` if the email is a disposable email.

### isAlias()

Returns `true` if the email is an alias. Example: `test+mail@gmail.com` is an alias of `test@gmail.com`.

### didYouMean()

If the email has a simple and obvious typo such as `gmail.cm` instead of `gmail.com` this method will return a string with a suggested correction, otherwise it will return an empty string.

It's recommended to use this feature using `javascript` in the client side with an option for them to correct the email before submitting the form

### isHighRisk()

Most service providers have a risk analysis tool. This method returns ``true` if the risk is high.
