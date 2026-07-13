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
 * @see    https://mailboxlayer.com/documentation API doc.
 *
 * @author Enrico Dias <enrico@enricodias.com>
 * @link   https://github.com/enricodias/emailValidator Github repository.
 */
class MailboxLayer extends ServiceProvider implements ServiceProviderInterface, HighRiskInterface
{
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
     * @param string $email Email to be validated.
     * @param ClientInterface $client PSR-18 HTTP client.
     * @param RequestFactoryInterface $requestFactory PSR-17 request factory used to build the API request.
     * @return boolean true if the validation occurs.
     */
    public function validate(string $email, ClientInterface $client, RequestFactoryInterface $requestFactory): bool
    {
        $this->email = $email;

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

        return $this->validateResponse(parent::getResponse());
    }

    /**
     * Checks if the email is valid. Disposable emails are also valid.
     *
     * @return boolean true if the email is valid.
     */
    public function isValid(): bool
    {
        return $this->result['format_valid'];
    }

    /**
     * Checks if the email is disposable.
     *
     * @return boolean true if the email is disposable.
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
     *
     * @return boolean true if the email is high risk.
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
}
