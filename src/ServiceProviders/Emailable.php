<?php

declare(strict_types=1);

namespace enricodias\EmailValidator\ServiceProviders;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Emailable
 *
 * Uses Emailable as a service provider to validate an email.
 *
 * @see https://emailable.com/docs/api/emails/ API doc.
 */
class Emailable extends ServiceProvider implements ServiceProviderInterface, HighRiskInterface
{
    /**
     * Default values returned by Emailable API.
     *
     * @var array
     */
    private $result = [
        'state'        => 'deliverable',
        'disposable'   => false,
        'did_you_mean' => null,
        'score'        => 100,
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
            'https://api.emailable.com/v1/verify',
            [
                'email'   => $email,
                'api_key' => $this->apiKey,
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
        if ($this->result['state'] === 'undeliverable') return false;

        return true;
    }

    /**
     * Checks if the email is disposable.
     */
    public function isDisposable(): bool
    {
        return $this->result['disposable'] === true;
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
     * Checks if the email is considered high risk based on the score returned by Emailable.
     *
     * The email is considered high risk if the score is below 50.
     */
    public function isHighRisk(): bool
    {
        return $this->result['score'] < 50;
    }

    /**
     * Processes a response from Emailable API.
     *
     * @param string[] $response Response from Emailable API.
     */
    private function validateResponse(array $response): bool
    {
        if (\array_key_exists('state', $response) === false) return false;

        $this->result = \array_merge($this->result, $response);

        return true;
    }
}
