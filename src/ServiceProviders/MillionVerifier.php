<?php

declare(strict_types=1);

namespace enricodias\EmailValidator\ServiceProviders;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * MillionVerifier
 *
 * Uses MillionVerifier as a service provider to validate an email.
 *
 * @see https://developer.millionverifier.com/#operation/single-verification API doc.
 */
class MillionVerifier extends ServiceProvider implements ServiceProviderInterface, HighRiskInterface
{
    /**
     * Default values returned by MillionVerifier API.
     *
     * @var array
     */
    private $result = [
        'email'         => '',
        'quality'       => 'good',
        'result'        => 'ok',
        'resultcode'    => 1,
        'subresult'     => '',
        'free'          => false,
        'role'          => false,
        'didyoumean'    => '',
        'credits'       => 0,
        'executiontime' => 0,
        'error'         => '',
        'livemode'      => true,
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
            'https://api.millionverifier.com/api/v3',
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
        if ($this->result['result'] === 'invalid') return false;

        return true;
    }

    /**
     * Checks if the email is disposable.
     */
    public function isDisposable(): bool
    {
        return $this->result['result'] === 'disposable';
    }

    /**
     * Tries to suggest a correction for common typos in the email.
     *
     * @return string A possible email suggestion or an empty string.
     */
    public function didYouMean(): string
    {
        return (string) $this->result['didyoumean'];
    }

    /**
     * Checks if the email risk score is considered high, based on the quality field returned by
     * MillionVerifier. Only a "good" quality is considered safe, "bad" and "risky" are high risk.
     */
    public function isHighRisk(): bool
    {
        return $this->result['quality'] !== 'good';
    }

    /**
     * Processes a response from MillionVerifier API.
     *
     * @param string[] $response Response from MillionVerifier API.
     */
    private function validateResponse(array $response): bool
    {
        if (\array_key_exists('result', $response) === false) return false;

        $this->result = \array_merge($this->result, $response);

        return true;
    }
}
