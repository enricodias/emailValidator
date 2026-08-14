<?php

declare(strict_types=1);

namespace enricodias\EmailValidator\ServiceProviders;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * MailboxLayer
 *
 * Uses MailboxLayer as a service provider to validate an email.
 *
 * @see https://docs.apilayer.com/mailboxlayer/docs/mailboxlayer-api-v-1-0-0#/default/checkEmail API doc.
 */
class MailboxLayer extends ServiceProvider implements QuotaAwareServiceProviderInterface, HighRiskInterface
{
    /**
     * @var \DateTimeImmutable|null
     */
    private $quotaCooldownUntil;

    /**
     * Default values returned by MailboxLayer API.
     *
     * @var array
     */
    private $result = [
        'email'        => '',
        'did_you_mean' => '',
        'user'         => '',
        'domain'       => '',
        'format_valid' => true,
        'mx_found'     => false,
        'smtp_check'   => false,
        'catch_all'    => false,
        'role'         => false,
        'disposable'   => false,
        'free'         => false,
        'score'        => 0,
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
            'https://apilayer.net/api/check',
            [
                'email'      => $email,
                'access_key' => $this->apiKey,
            ],
            ['Accept' => 'application/json']
        );

        if (parent::request($client, $request) === false) return false;

        if ($this->isMonthlyLimitResponse(parent::getResponse())) {
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
        return $this->result['format_valid'];
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
        if ($this->result['score'] < 0.5) return true;

        return false;
    }

    /**
     * Processes a response from mailgun API.
     *
     * @param string[] $response Response from mailgun API.
     */
    private function validateResponse(array $response): bool
    {
        if (\array_key_exists('format_valid', $response) === false) return false;

        $this->result = \array_merge($this->result, $response);

        return true;
    }

    private function isMonthlyLimitResponse(array $response): bool
    {
        if (parent::getResponseStatusCode() !== 429) return false;

        if (! \array_key_exists('message', $response) || ! \is_string($response['message'])) return false;

        return \stripos($response['message'], 'monthly API rate limit') !== false;
    }
}
