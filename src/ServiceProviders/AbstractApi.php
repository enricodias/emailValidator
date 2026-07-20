<?php

declare(strict_types=1);

namespace enricodias\EmailValidator\ServiceProviders;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * AbstractApi
 *
 * Uses Abstract API as a service provider to validate an email.
 *
 * @see https://docs.abstractapi.com/api/email-validation API doc.
 */
class AbstractApi extends ServiceProvider implements ServiceProviderInterface, HighRiskInterface
{
    /**
     * Default values returned by Abstract API.
     *
     * @var array
     */
    private $result = [
        'email'               => '',
        'autocorrect'         => '',
        'deliverability'      => 'UNKNOWN',
        'quality_score'       => 0,
        'is_valid_format'     => ['value' => true],
        'is_free_email'       => ['value' => false],
        'is_disposable_email' => ['value' => false],
        'is_role_email'       => ['value' => false],
        'is_catchall_email'   => ['value' => false],
        'is_mx_found'         => ['value' => false],
        'is_smtp_valid'       => ['value' => false],
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
            'https://emailvalidation.abstractapi.com/v1',
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
        return (bool) $this->result['is_valid_format']['value'];
    }

    /**
     * Checks if the email is disposable.
     */
    public function isDisposable(): bool
    {
        return (bool) $this->result['is_disposable_email']['value'];
    }

    /**
     * Tries to suggest a correction for common typos in the email.
     *
     * @return string A possible email suggestion or an empty string.
     */
    public function didYouMean(): string
    {
        return (string) $this->result['autocorrect'];
    }

    /**
     * Checks if the email quality score is considered high risk.
     */
    public function isHighRisk(): bool
    {
        if ($this->result['quality_score'] < 0.5) return true;

        return false;
    }

    /**
     * Processes a response from Abstract API.
     *
     * @param string[] $response Response from Abstract API.
     */
    private function validateResponse(array $response): bool
    {
        if (\array_key_exists('is_valid_format', $response) === false) return false;

        $this->result = \array_merge($this->result, $response);

        return true;
    }
}
