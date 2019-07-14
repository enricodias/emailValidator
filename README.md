# emailValidator

A simple class to validate emails using ```respect/validation``` and <a href="https://validator.pizza">validator.pizza</a>, a free API to check if domains are disposable.

## Installation

Require this package with Composer in the root directory of your project.

```bash
composer require enricodias/emailValidator
```

## Usage

The function ```\enricodias\emailValidator::validate($email)``` returns ```true``` if ```$email``` is a valid email.

```php
if (\enricodias\emailValidator::validate($email)) {
    echo 'Valid';
} else {
    echo 'Invalid';
}
```

## How it works

The email is considered invalid if it fails on the ```respect/validation``` check OR the email's domain doesn't have valid MX records OR the email is from a known disposable domain.

Since <a href="https://validator.pizza">validator.pizza</a> limits how many request you can make per hour, no request is made if the email doesn't pass on the ```respect/validation``` validation. The function will also return ```true``` if validator.pizza returns a rate limit error.
