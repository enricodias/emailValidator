<?php

declare(strict_types=1);

namespace enricodias\EmailValidator\ServiceProviders;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * DeBounce
 *
 * Uses DeBounce as a service provider to validate an email.
 *
 * @see https://developers.debounce.com/api-reference/endpoint/single-validation API doc.
 */
class DeBounce extends ServiceProvider implements ServiceProviderInterface, HighRiskInterface
{
    /**
     * Default values returned by DeBounce API.
     *
     * @var array
     */
    private $result = [
        'email'              => '',
        'code'               => '5',
        'role'               => 'false',
        'free_email'         => 'false',
        'result'             => 'Safe to Send',
        'reason'             => '',
        'send_transactional' => '1',
        'did_you_mean'       => '',
    ];

    /**
     * Validates an email address.
     *
     * @return boolean true if the validation occurs.
     */
    public function validate(string $email, ClientInterface $client, RequestFactoryInterface $requestFactory): bool
    {
        $this->email = $email;

        $request = $this->buildRequest(
            $requestFactory,
            'https://api.debounce.io/v1/',
            [
                'email' => $email,
                'api'   => $this->apiKey,
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
        return $this->result['result'] !== 'Invalid';
    }

    /**
     * Checks if the email is disposable.
     */
    public function isDisposable(): bool
    {
        return $this->result['code'] === '3';
    }

    /**
     * Tries to suggest a correction for common typos in the email.
     *
     * @return string A possible email suggestion or an empty string.
     */
    public function didYouMean(): string
    {
        return (string) $this->result['did_you_mean'];
    }

    /**
     * Checks if the email is considered high risk.
     *
     * The email is considered high risk if DeBounce classifies the result as risky.
     */
    public function isHighRisk(): bool
    {
        return $this->result['result'] === 'Risky';
    }

    /**
     * Processes a response from DeBounce API.
     *
     * @param string[] $response Response from DeBounce API.
     */
    private function validateResponse(array $response): bool
    {
        if (\array_key_exists('debounce', $response) === false) return false;

        $this->result = \array_merge($this->result, (array) $response['debounce']);

        return true;
    }
}
