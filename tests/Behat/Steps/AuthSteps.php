<?php

declare(strict_types=1);

namespace App\Tests\Behat\Steps;

/**
 * Authentication steps: register, login (+ throttling), profile, refresh.
 *
 * These exercise the real auth endpoints end to end. Identity creation helpers
 * (createPlayer, tokenFor, actAs) live on the host context.
 */
trait AuthSteps
{
    private const string DEFAULT_PASSWORD = 'password123';

    /**
     * @Given a registered player with email :email and password :password
     */
    public function aRegisteredPlayerWithEmailAndPassword(string $email, string $password): void
    {
        $this->createPlayerWithPassword($email, $password);
    }

    /**
     * @When I register with email :email password :password and confirmation :confirmation
     */
    public function iRegisterWith(string $email, string $password, string $confirmation): void
    {
        $this->actAs(null);
        $this->sendRequest('POST', '/api/auth/register', \json_encode([
            'email'                => $email,
            'password'             => $password,
            'passwordConfirmation' => $confirmation,
        ], \JSON_THROW_ON_ERROR));
    }

    /**
     * @When I log in with email :email and password :password
     */
    public function iLogInWith(string $email, string $password): void
    {
        $this->actAs(null);
        $this->sendRequest('POST', '/api/auth/login', \json_encode([
            'email'    => $email,
            'password' => $password,
        ], \JSON_THROW_ON_ERROR));
    }

    /**
     * Fires N login attempts with a wrong password to trip the throttle (5/min).
     *
     * @When I log in :count times with the wrong password for :email
     */
    public function iLogInTimesWithWrongPassword(int $count, string $email): void
    {
        $this->actAs(null);
        for ($i = 0; $i < $count; $i++) {
            $this->sendRequest('POST', '/api/auth/login', \json_encode([
                'email'    => $email,
                'password' => 'definitely-wrong-password',
            ], \JSON_THROW_ON_ERROR));
        }
    }

    /**
     * @When I request my profile
     */
    public function iRequestMyProfile(): void
    {
        $this->sendRequest('GET', '/api/auth/me', null);
    }

    /**
     * @When I request my profile without a token
     */
    public function iRequestMyProfileWithoutAToken(): void
    {
        $this->actAs(null);
        $this->sendRequest('GET', '/api/auth/me', null);
    }

    /**
     * Logs in to obtain a refresh_token, then exchanges it for a fresh JWT.
     *
     * @When I refresh my session
     */
    public function iRefreshMySession(): void
    {
        $this->actAs(null);
        $this->sendRequest('POST', '/api/auth/login', \json_encode([
            'email'    => $this->currentUserEmail,
            'password' => self::DEFAULT_PASSWORD,
        ], \JSON_THROW_ON_ERROR));

        $refreshToken = $this->getDecodedResponse()['refresh_token'] ?? '';
        $this->sendRequest('POST', '/api/auth/refresh', \json_encode([
            'refresh_token' => $refreshToken,
        ], \JSON_THROW_ON_ERROR));
    }

    /**
     * @When I refresh my session with an invalid token
     */
    public function iRefreshWithAnInvalidToken(): void
    {
        $this->actAs(null);
        $this->sendRequest('POST', '/api/auth/refresh', \json_encode([
            'refresh_token' => 'not-a-valid-refresh-token',
        ], \JSON_THROW_ON_ERROR));
    }

    /**
     * @Then I receive an authentication token
     */
    public function iReceiveAnAuthenticationToken(): void
    {
        $data = $this->getDecodedResponse();
        if (empty($data['token'])) {
            throw new \RuntimeException('Expected a non-empty "token" in the response, none found.');
        }
    }

    /**
     * @Then I do not receive an authentication token
     */
    public function iDoNotReceiveAnAuthenticationToken(): void
    {
        $data = $this->getDecodedResponse();
        if (\array_key_exists('token', $data)) {
            throw new \RuntimeException('Expected no "token" in the response, but one was present.');
        }
    }

    /**
     * @Then I receive a refresh token
     */
    public function iReceiveARefreshToken(): void
    {
        $data = $this->getDecodedResponse();
        if (empty($data['refresh_token'])) {
            throw new \RuntimeException('Expected a non-empty "refresh_token" in the response, none found.');
        }
    }

    /**
     * @Then my profile email is :email
     */
    public function myProfileEmailIs(string $email): void
    {
        $data = $this->getDecodedResponse();
        if (($data['email'] ?? null) !== $email) {
            throw new \RuntimeException(\sprintf('Expected profile email "%s" but got "%s".', $email, $data['email'] ?? 'null'));
        }
    }
}
