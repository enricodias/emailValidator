<?php

declare(strict_types=1);

namespace enricodias\EmailValidator\ServiceProviders;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * ZeroBounce
 *
 * Uses ZeroBounce as a service provider to validate an email.
 *
 * @see https://www.zerobounce.net/docs/email-validation-api-quickstart/v2-validate-emails API doc.
 */
class ZeroBounce extends ServiceProvider implements QuotaAwareServiceProviderInterface, HighRiskInterface
{
    /**
     * @var \DateTimeImmutable|null
     */
    private $quotaCooldownUntil;

    /**
     * Default values returned by ZeroBounce API.
     *
     * @var array
     */
    private $result = [
        'address'           => '',
        'status'            => 'valid',
        'sub_status'        => '',
        'free_email'        => false,
        'catchall_domain'   => false,
        'did_you_mean'      => null,
        'account'           => '',
        'domain'            => '',
        'domain_age_days'   => null,
        'active_in_days'    => null,
        'active_first_seen' => null,
        'smtp_provider'     => null,
        'mx_found'          => 'false',
        'mx_record'         => null,
        'firstname'         => null,
        'lastname'          => null,
        'gender'            => null,
        'country'           => null,
        'region'            => null,
        'city'              => null,
        'zipcode'           => null,
        'processed_at'      => '',
    ];

    /**
     * Validates an email address.
     *
     * @return boolean true if the validation occurs.
     */
    public function validate(string $email, ClientInterface $client, RequestFactoryInterface $requestFactory): bool
    {
        $this->email = $email;
        $this->quotaCooldownUntil = null;

        $request = $this->buildRequest(
            $requestFactory,
            'https://api.zerobounce.net/v2/validate',
            [
                'email'   => $email,
                'api_key' => $this->apiKey,
            ],
            ['Accept' => 'application/json']
        );

        if (parent::request($client, $request) === false) return false;

        if ($this->isCreditError(parent::getResponse())) {
            $this->quotaCooldownUntil = parent::nextUtcMonth();

            return false;
        }

        return $this->validateResponse(parent::getResponse());
    }

    public function getQuotaCacheIdentity(): string
    {
        return parent::getQuotaCacheIdentity();
    }

    public function getQuotaCooldownUntil(): ?\DateTimeImmutable
    {
        return $this->quotaCooldownUntil;
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
        return $this->result['sub_status'] === 'disposable';
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
     * Checks if the email is considered high risk, calculated internally since ZeroBounce
     * doesn't return a risk score.
     *
     * The email is considered high risk if the status is anything other than a clean valid
     * result, if the domain accepts any address (catchall), if the mail server was not found,
     * or if it is flagged as a role account, disposable, toxic, or a known spam trap.
     */
    public function isHighRisk(): bool
    {
        $highRiskStatuses = ['catch-all', 'unknown', 'spamtrap', 'abuse', 'do_not_mail'];

        if (\in_array($this->result['status'], $highRiskStatuses, true)) return true;

        if ($this->result['catchall_domain'] === true) return true;

        if ($this->result['mx_found'] === 'false') return true;

        $highRiskSubStatuses = ['role_based', 'role_based_catch_all', 'disposable', 'toxic', 'possible_trap', 'global_suppression'];

        if (\in_array($this->result['sub_status'], $highRiskSubStatuses, true)) return true;

        return false;
    }

    /**
     * Processes a response from ZeroBounce API.
     *
     * @param string[] $response Response from ZeroBounce API.
     */
    private function validateResponse(array $response): bool
    {
        if (\array_key_exists('status', $response) === false) return false;

        $this->result = \array_merge($this->result, $response);

        return true;
    }

    private function isCreditError(array $response): bool
    {
        if (!\array_key_exists('error', $response) || !\is_string($response['error'])) return false;

        return \stripos($response['error'], 'account ran out of credits') !== false;
    }
}
