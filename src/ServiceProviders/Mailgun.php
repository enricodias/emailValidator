<?php

declare(strict_types=1);

namespace enricodias\EmailValidator\ServiceProviders;

use GuzzleHttp\Psr7\Request;

/**
 * Mailgun
 *
 * Uses Mailgun as a service provider to validate an email.
 *
 * @see    https://documentation.mailgun.com/en/latest/api-email-validation.html API doc.
 *
 * @author Enrico Dias <enrico@enricodias.com>
 * @link   https://github.com/enricodias/emailValidator Github repository.
 */
class Mailgun extends ServiceProvider implements ServiceProviderInterface
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
     * @param string $email Email to be validated.
     * @param object GuzzleHttp\Client $client.
     * @return boolean true if the validation occurs.
     */
    public function validate(string $email, \GuzzleHttp\Client $client): bool
    {
        $this->email = $email;

        $request = new Request(
            'GET',
            'https://api.mailgun.net/v4/address/validate',
            [
                'auth' => [
                    'api:'.$this->apiKey,
                ],
                'query' => [
                    'address' => $email,
                ],
                'Accept' => 'application/json',
            ]
        );

        if (parent::request($client, $request) === false) return false;

        return $this->validateResponse(parent::getResponse());
    }

    /**
     * Checks if the email is valid. Disposable emails are also valid.
     *
     * @return boolean true if the email is valid.
     */
    public function isValid(): bool
    {
        if ($this->result['result'] === 'undeliverable') return false;

        return true;
    }

    /**
     * Checks if the email is disposable.
     *
     * @return boolean true if the email is disposable.
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
     *
     * @return boolean true if the email is high risk.
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
