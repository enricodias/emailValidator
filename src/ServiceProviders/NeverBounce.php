<?php

declare(strict_types=1);

namespace enricodias\EmailValidator\ServiceProviders;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * NeverBounce
 *
 * Uses NeverBounce as a service provider to validate an email.
 *
 * @see https://developers.neverbounce.com/reference/single-check API doc.
 */
class NeverBounce extends ServiceProvider implements ServiceProviderInterface, HighRiskInterface
{
    /**
     * Default values returned by NeverBounce API.
     *
     * @var array
     */
    private $result = [
        'status'               => '',
        'result'               => 'valid',
        'flags'                => [],
        'suggested_correction' => '',
        'execution_time'       => 0,
    ];

    /**
     * Validates an email address.
     *
     * NeverBounce doesn't support aliases, the email is validated without alias.
     *
     * @return boolean true if the validation occurs.
     */
    public function validate(string $email, ClientInterface $client, RequestFactoryInterface $requestFactory): bool
    {
        $this->email = $email;

        $domain = \strstr($email, '@');
        $email = \strstr($email, '@', true);

        if (\stripos($email, '+') !== false) $email = \strstr($email, '+', true);

        $email .= $domain;

        $request = $this->buildRequest(
            $requestFactory,
            'https://api.neverbounce.com/v4/single/check',
            [
                'key'   => $this->apiKey,
                'email' => $email,
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
        if ($this->result['result'] === 'disposable') return true;

        return false;
    }


    /**
     * Checks if the email is considered high risk, calculated internally.
     *
     * The email is considered high risk if the domain accepts any address (catchall) or if
     * it is flagged as a role account, a spam trap, or a host designed to look like a big-time provider.
     */
    public function isHighRisk(): bool
    {
        if ($this->result['result'] === 'catchall') return true;

        $highRiskFlags = ['role_account', 'spamtrap_network', 'squatter_host'];

        foreach ($highRiskFlags as $flag) {
            if (\in_array($flag, $this->result['flags'], true)) return true;
        }

        return false;
    }

    /**
     * Tries to suggest a correction for common typos in the email.
     *
     * Since the email is validated without alias, only the domain suggestion is valid.
     *
     * @return string A possible email suggestion or an empty string.
     */
    public function didYouMean(): string
    {
        if ($this->result['suggested_correction'] === '') return '';

        if (\stripos($this->email, '+') === false) return $this->result['suggested_correction'];

        $domain = \strstr($this->result['suggested_correction'], '@');
        $email = \strstr($this->email, '@', true);
        $email = \strstr($email, '+', true) . $domain;

        return $email;
    }

    /**
     * Processes a response from NeverBounce API.
     *
     * @param string[] $response Response from NeverBounce API.
     */
    private function validateResponse(array $response): bool
    {
        if (\array_key_exists('status', $response) && $response['status'] !== 'success') return false;

        $this->result = \array_merge($this->result, $response);

        return true;
    }
}
