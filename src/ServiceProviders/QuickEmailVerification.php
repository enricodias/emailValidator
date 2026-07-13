<?php

declare(strict_types=1);

namespace enricodias\EmailValidator\ServiceProviders;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * QuickEmailVerification
 *
 * Uses QuickEmailVerification as a service provider to validate an email.
 *
 * @see https://docs.quickemailverification.com/email-verification-api/verify-an-email-address API doc.
 */
class QuickEmailVerification extends ServiceProvider implements ServiceProviderInterface, HighRiskInterface
{
    /**
     * Default values returned by QuickEmailVerification API.
     *
     * @var array
     */
    private $result = [
        'result'       => 'valid',
        'reason'       => '',
        'disposable'   => false,
        'accept_all'   => false,
        'role'         => false,
        'free'         => false,
        'email'        => '',
        'user'         => '',
        'domain'       => '',
        'mx_record'    => '',
        'mx_domain'    => '',
        'safe_to_send' => true,
        'did_you_mean' => '',
        'success'      => false,
        'message'      => '',
    ];

    /**
     * Validates an email address.
     */
    public function validate(string $email, ClientInterface $client, RequestFactoryInterface $requestFactory): bool
    {
        $this->email = $email;

        $request = $this->buildRequest(
            $requestFactory,
            'https://api.quickemailverification.com/v1/verify',
            [
                'email'  => $email,
                'apikey' => $this->apiKey,
            ],
            ['Accept' => 'application/json']
        );

        if (parent::request($client, $request) === false) return false;

        return $this->validateResponse(parent::getResponse());
    }

    /**
     * Checks if the email is valid. Disposable emails are also valid.
     */
    public function isValid(): bool
    {
        if ($this->result['result'] === 'invalid') return false;

        return true;
    }

    /**
     * Checks if the email is disposable.
     */
    public function isDisposable(): bool
    {
        return $this->toBool($this->result['disposable']);
    }

    /**
     * Tries to suggest a correction for common typos in the email.
     */
    public function didYouMean(): string
    {
        return (string) $this->result['did_you_mean'];
    }

    /**
     * Checks if the email risk score is considered high.
     */
    public function isHighRisk(): bool
    {
        return $this->toBool($this->result['safe_to_send']) === false;
    }

    /**
     * Processes a response from QuickEmailVerification API.
     */
    private function validateResponse(array $response): bool
    {
        if (\array_key_exists('success', $response) === false || $this->toBool($response['success']) === false) return false;

        $this->result = \array_merge($this->result, $response);

        return true;
    }

    /**
     * QuickEmailVerification returns boolean fields as quoted strings (f.e. "true"/"false")
     * instead of native JSON booleans, so this normalizes both formats.
     */
    private function toBool($value): bool
    {
        return $value === true || $value === 'true';
    }
}
