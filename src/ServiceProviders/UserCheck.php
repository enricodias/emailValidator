<?php

declare(strict_types=1);

namespace enricodias\EmailValidator\ServiceProviders;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * UserCheck (old MailCheckAi & ValidatorPizza)
 *
 * Uses UserCheck as a service provider to validate an email.
 *
 * The API key is optional. Requests without one are still accepted, but an API key
 * is required to use a paid plan's higher rate limits.
 *
 * @see    https://www.usercheck.com/docs/api/introduction UserCheck API.
 *
 * @author Enrico Dias <enrico@enricodias.com>
 * @link   https://github.com/enricodias/emailValidator Github repository.
 */
class UserCheck extends ServiceProvider implements ServiceProviderInterface
{
    /**
     * Default values returned by UserCheck API.
     *
     * @var array
     */
    private $result = [
        'status'             => 0,
        'domain'             => '',
        'mx'                 => false,
        'disposable'         => false,
        'alias'              => false,
        'did_you_mean'       => null,
        'remaining_requests' => 120,
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

        $headers = ['Accept' => 'application/json'];

        if ($this->apiKey !== '') $headers['Authorization'] = 'Bearer ' . $this->apiKey;

        $request = $this->buildRequest(
            $requestFactory,
            'https://api.usercheck.com/email/' . \rawurlencode($email),
            [],
            $headers
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
        if ($this->result['status'] !== 0) {

            // we should assume the email to be valid if we get any status other than 400 from the API
            if ($this->result['status'] === 400) return false;

        }

        return true;
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
        if ($this->result['did_you_mean'] === null) return '';

        $email = \str_ireplace($this->result['domain'], $this->result['did_you_mean'], $this->email);

        return $email;
    }

    /**
     * Processes a response from UserCheck API.
     *
     * @param string[] $response Response from UserCheck API.
     */
    private function validateResponse(array $response): bool
    {
        if (\array_key_exists('status', $response) === false || !$this->checkValidStatus((int) $response['status'])) return false;

        $this->result['status'] = $response['status'];

        if ($response['status'] === 200) $this->result = $response;

        return true;
    }

    /**
     * Validates the status returned by the UserCheck's API to verify whether or not we can trust the response.
     * The only valid values are 200, 400 and 429.
     *
     * @param int $status Status code.
     * @return boolean true if the status code is valid.
     */
    private function checkValidStatus(int $status): bool
    {
        if ($status !== 200 && $status !== 400 && $status !== 429) return false;

        return true;
    }
}
