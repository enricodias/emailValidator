<?php

declare(strict_types=1);

namespace enricodias\EmailValidator\ServiceProviders;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Clearout
 *
 * Uses Clearout as a service provider to validate an email.
 *
 * @see https://docs.clearout.io/developers/api/email-verify API doc.
 */
class Clearout extends ServiceProvider implements ServiceProviderInterface, HighRiskInterface
{
    /**
     * Default values returned by Clearout API.
     *
     * @var array
     */
    private $result = [
        'email_address'           => '',
        'safe_to_send'            => 'yes',
        'status'                  => 'valid',
        'disposable'              => 'no',
        'free'                    => 'no',
        'role'                    => 'no',
        'gibberish'               => 'no',
        'suggested_email_address' => null,
        'bounce_type'             => null,
    ];

    /**
     * Validates an email address.
     *
     * @return boolean true if the validation occurs.
     */
    public function validate(string $email, ClientInterface $client, RequestFactoryInterface $requestFactory): bool
    {
        $this->email = $email;

        $request = $this->buildJsonRequest(
            $requestFactory,
            'https://api.clearout.io/v2/email_verify/instant',
            [
                'email' => $email,
            ],
            [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
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
        if ($this->result['status'] === 'invalid') return false;

        return true;
    }

    /**
     * Checks if the email is disposable.
     */
    public function isDisposable(): bool
    {
        return $this->result['disposable'] === 'yes';
    }

    /**
     * Tries to suggest a correction for common typos in the email.
     *
     * @return string A possible email suggestion or an empty string.
     */
    public function didYouMean(): string
    {
        return (string) $this->result['suggested_email_address'];
    }

    /**
     * Checks if the email is considered high risk, calculated internally since Clearout doesn't
     * return a risk score.
     *
     * The email is considered high risk if the status is anything other than a clean valid
     * result, if Clearout doesn't consider it safe to send, or if it is flagged as disposable,
     * a role account, or gibberish.
     */
    public function isHighRisk(): bool
    {
        $highRiskStatuses = ['catch-all', 'unknown', 'spamtrap'];

        if (\in_array($this->result['status'], $highRiskStatuses, true) ||
            $this->result['safe_to_send'] === 'no' ||
            $this->result['disposable'] === 'yes' ||
            $this->result['role'] === 'yes' ||
            $this->result['gibberish'] === 'yes'
        ) {
            return true;
        }

        return false;
    }

    /**
     * Processes a response from Clearout API.
     *
     * @param string[] $response Response from Clearout API.
     */
    private function validateResponse(array $response): bool
    {
        if (\array_key_exists('status', $response) === false || $response['status'] !== 'success') return false;

        if (\array_key_exists('data', $response) === false) return false;

        $this->result = \array_merge($this->result, (array) $response['data']);

        return true;
    }
}
