<?php

declare(strict_types=1);

namespace enricodias\EmailValidator\ServiceProviders;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Kickbox
 *
 * Uses Kickbox as a service provider to validate an email.
 *
 * @see https://docs.kickbox.com/docs/single-verification-api API doc.
 */
class Kickbox extends ServiceProvider implements ServiceProviderInterface, HighRiskInterface
{
    /**
     * Default values returned by kickbox API.
     *
     * @var array
     */
    private $result = [
        'result'       => 'deliverable',
        'reason'       => '',
        'role'         => false,
        'free'         => false,
        'disposable'   => false,
        'accept_all'   => false,
        'did_you_mean' => '',
        'sendex'       => 1,
        'email'        => '',
        'user'         => '',
        'domain'       => '',
        'success'      => false,
        'message'      => null,
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
            'https://api.kickbox.com/v2/verify',
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
        if ($this->result['result'] === 'undeliverable') return false;

        return true;
    }

    /**
     * Checks if the email is disposable.
     */
    public function isDisposable(): bool
    {
        return $this->result['disposable'];
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
     * Checks if the email risk score is considered high.
     */
    public function isHighRisk(): bool
    {
        if ($this->result['sendex'] < 0.5) return true;

        return false;
    }

    /**
     * Processes a response from mailgun API.
     *
     * @param string[] $response Response from mailgun API.
     */
    private function validateResponse(array $response): bool
    {
        if (\array_key_exists('success', $response) === false || $response['success'] !== true) return false;

        $this->result = \array_merge($this->result, $response);

        return true;
    }
}
