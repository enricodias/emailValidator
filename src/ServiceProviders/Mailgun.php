<?php

declare(strict_types=1);

namespace enricodias\EmailValidator\ServiceProviders;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Mailgun
 *
 * Uses Mailgun as a service provider to validate an email.
 *
 * @see https://documentation.mailgun.com/en/latest/api-email-validation.html API doc.
 */
class Mailgun extends ServiceProvider implements ServiceProviderInterface, HighRiskInterface
{
    /**
     * Default values returned by mailgun API.
     *
     * @var array
     */
    private $result = [
        'address'               => '',
        'did_you_mean'          => '',
        'is_disposable_address' => false,
        'is_role_address'       => false,
        'reason'                => [],
        'result'                => 'deliverable',
        'risk'                  => 'low',
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
            'https://api.mailgun.net/v4/address/validate',
            [
                'address' => $email,
            ],
            [
                'Accept'        => 'application/json',
                'Authorization' => 'Basic ' . \base64_encode('api:' . $this->apiKey),
            ]
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
        return $this->result['is_disposable_address'];
    }

    /**
     * Tries to suggest a correction for common typos in the email.
     *
     * ! Currently Mailgun never returns a suggestion.
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
        if ($this->result['risk'] === 'high') return true;

        return false;
    }

    /**
     * Processes a response from mailgun API.
     *
     * @param string[] $response Response from mailgun API.
     */
    private function validateResponse(array $response): bool
    {
        $validResults = ['undeliverable', 'deliverable', 'do_not_send'];

        if (\array_key_exists('result', $response) === false || \in_array($response['result'], $validResults, true) === false) return false;

        $this->result = \array_merge($this->result, $response);

        return true;
    }
}
