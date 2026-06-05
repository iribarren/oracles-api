<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Phase 4 integration tests: auth flows, token refresh, admin panel access,
 * guest flow, and the full register → login → play → resume end-to-end flow.
 */
class AuthFlowTest extends WebTestCase
{
    private KernelBrowser $browser;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->browser = static::createClient();
        $this->em      = static::getContainer()->get(EntityManagerInterface::class);
        $this->purgeDatabase();
    }

    protected function tearDown(): void
    {
        $this->purgeDatabase();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function purgeDatabase(): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $conn->executeStatement('DELETE FROM journal_entries');
        $conn->executeStatement('DELETE FROM roll_results');
        $conn->executeStatement('DELETE FROM books');
        $conn->executeStatement('DELETE FROM attributes');
        $conn->executeStatement('DELETE FROM game_sessions');
        $conn->executeStatement('DELETE FROM refresh_tokens');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function createUser(string $email, string $password): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user   = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_PLAYER']);
        $user->setPassword($hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function getJwt(string $email, string $password): string
    {
        $this->browser->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            \json_encode(['email' => $email, 'password' => $password], \JSON_THROW_ON_ERROR)
        );
        $data = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $data['token'];
    }

    /**
     * Logs in via /api/auth/login and returns both token and refresh_token.
     *
     * @return array{token: string, refresh_token: string}
     */
    private function login(string $email, string $password): array
    {
        $this->browser->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            \json_encode(['email' => $email, 'password' => $password], \JSON_THROW_ON_ERROR)
        );

        return \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function register(string $email, string $password, string $passwordConfirmation): void
    {
        $this->browser->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            \json_encode([
                'email'                => $email,
                'password'             => $password,
                'passwordConfirmation' => $passwordConfirmation,
            ], \JSON_THROW_ON_ERROR)
        );
    }

    private function createGame(?string $token = null): string
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $this->browser->request('POST', '/api/game', [], [], $headers, '{}');
        $data = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $data['id'];
    }

    private function getLastStatusCode(): int
    {
        return $this->browser->getResponse()->getStatusCode();
    }

    // -------------------------------------------------------------------------
    // 1. Registration validation
    // -------------------------------------------------------------------------

    public function testRegisterValidationFailsOnEmptyEmail(): void
    {
        $this->register('', 'password123', 'password123');

        $this->assertSame(422, $this->getLastStatusCode());
    }

    public function testRegisterValidationFailsOnInvalidEmail(): void
    {
        $this->register('notanemail', 'password123', 'password123');

        $this->assertSame(422, $this->getLastStatusCode());
    }

    public function testRegisterValidationFailsOnShortPassword(): void
    {
        $this->register('user@test.com', 'short', 'short');

        $this->assertSame(422, $this->getLastStatusCode());
    }

    public function testRegisterValidationFailsOnPasswordMismatch(): void
    {
        $this->register('user@test.com', 'password123', 'different123');

        $this->assertSame(422, $this->getLastStatusCode());
    }

    // -------------------------------------------------------------------------
    // 2. Registration success
    // -------------------------------------------------------------------------

    public function testRegisterCreatesUserAndReturnsToken(): void
    {
        $this->register('newuser@test.com', 'password123', 'password123');

        $this->assertSame(201, $this->getLastStatusCode());

        $data = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('token', $data);
        $this->assertNotEmpty($data['token']);
    }

    // -------------------------------------------------------------------------
    // 3. Duplicate email (email enumeration prevention)
    // -------------------------------------------------------------------------

    public function testRegisterWithDuplicateEmailReturns201WithoutError(): void
    {
        $this->register('dup@test.com', 'password123', 'password123');
        $this->assertSame(201, $this->getLastStatusCode());

        // Second registration with same email — must also return 201 (no error exposed)
        $this->register('dup@test.com', 'password123', 'password123');
        $this->assertSame(201, $this->getLastStatusCode());

        $data = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('error', $data);
    }

    // -------------------------------------------------------------------------
    // 4. Login
    // -------------------------------------------------------------------------

    public function testLoginReturnsTokenAndRefreshToken(): void
    {
        $this->createUser('player@test.com', 'password123');

        $data = $this->login('player@test.com', 'password123');

        $this->assertSame(200, $this->getLastStatusCode());
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('refresh_token', $data);
        $this->assertNotEmpty($data['token']);
        $this->assertNotEmpty($data['refresh_token']);
    }

    public function testLoginWithWrongPasswordReturns401(): void
    {
        $this->createUser('player@test.com', 'password123');

        $this->browser->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            \json_encode(['email' => 'player@test.com', 'password' => 'wrongpassword'], \JSON_THROW_ON_ERROR)
        );

        $this->assertSame(401, $this->getLastStatusCode());
    }

    // -------------------------------------------------------------------------
    // 5. Me endpoint
    // -------------------------------------------------------------------------

    public function testMeReturnsUserDataWhenAuthenticated(): void
    {
        $this->createUser('me@test.com', 'password123');
        $token = $this->getJwt('me@test.com', 'password123');

        $this->browser->request(
            'GET',
            '/api/auth/me',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertSame(200, $this->getLastStatusCode());

        $data = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('roles', $data);
        $this->assertSame('me@test.com', $data['email']);
        $this->assertContains('ROLE_PLAYER', $data['roles']);
    }

    public function testMeReturns401WhenNotAuthenticated(): void
    {
        // The access_control rule `^/api/auth/me$ → IS_AUTHENTICATED_FULLY` makes the
        // JWT firewall reject the unauthenticated request through its entry point.
        $this->browser->request('GET', '/api/auth/me');

        $this->assertSame(401, $this->getLastStatusCode());
    }

    // -------------------------------------------------------------------------
    // 6. Token refresh
    // -------------------------------------------------------------------------

    public function testRefreshTokenReturnsNewToken(): void
    {
        // The gesdinet refresh route is wired in config/routes/security.yaml and handled
        // by the firewall's refresh_jwt authenticator. A valid refresh_token yields a new JWT.
        $this->createUser('refresh@test.com', 'password123');
        $loginData = $this->login('refresh@test.com', 'password123');

        $this->assertArrayHasKey('refresh_token', $loginData, 'Login must return a refresh_token.');
        $this->assertNotEmpty($loginData['refresh_token']);

        $this->browser->request(
            'POST',
            '/api/auth/refresh',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            \json_encode(['refresh_token' => $loginData['refresh_token']], \JSON_THROW_ON_ERROR)
        );

        $this->assertSame(200, $this->getLastStatusCode());
        $data = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('token', $data, 'Refresh must return a new JWT token.');
        $this->assertNotEmpty($data['token']);
    }

    public function testRefreshWithInvalidTokenReturns401(): void
    {
        $this->browser->request(
            'POST',
            '/api/auth/refresh',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            \json_encode(['refresh_token' => 'this-is-not-a-valid-refresh-token'], \JSON_THROW_ON_ERROR)
        );

        $this->assertSame(401, $this->getLastStatusCode());
    }

    // -------------------------------------------------------------------------
    // 7. Full end-to-end flow
    // -------------------------------------------------------------------------

    public function testFullPlayerFlow(): void
    {
        // Step 1: Register a new player
        $this->register('e2e@test.com', 'password123', 'password123');
        $this->assertSame(201, $this->getLastStatusCode());

        // Step 2: Login to get refresh_token (register only returns token, not refresh_token)
        $loginData = $this->login('e2e@test.com', 'password123');
        $this->assertSame(200, $this->getLastStatusCode());
        $token        = $loginData['token'];
        $refreshToken = $loginData['refresh_token'];

        // Step 3: Create a game session (authenticated)
        $gameId = $this->createGame($token);
        $this->assertNotEmpty($gameId);

        // Step 4: Verify the session is in /api/player/sessions
        $this->browser->request(
            'GET',
            '/api/player/sessions',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertSame(200, $this->getLastStatusCode());
        $sessions = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertCount(1, $sessions);
        $this->assertSame($gameId, $sessions[0]['id']);

        // Step 5: Get the game by ID with the player's token → 200
        $this->browser->request(
            'GET',
            '/api/game/' . $gameId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertSame(200, $this->getLastStatusCode());

        // Step 6: Refresh the token — the refresh_token from login yields a fresh JWT.
        $this->browser->request(
            'POST',
            '/api/auth/refresh',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            \json_encode(['refresh_token' => $refreshToken], \JSON_THROW_ON_ERROR)
        );
        $this->assertSame(200, $this->getLastStatusCode());
        $refreshed = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('token', $refreshed);

        // Step 7: The refreshed token is valid — use it to access the game.
        $this->browser->request(
            'GET',
            '/api/game/' . $gameId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $refreshed['token']]
        );
        $this->assertSame(200, $this->getLastStatusCode());
    }

    // -------------------------------------------------------------------------
    // 8. Admin panel inaccessible to players
    // -------------------------------------------------------------------------

    public function testPlayerCannotAccessAdminPanel(): void
    {
        $this->createUser('player@test.com', 'password123');
        $token = $this->getJwt('player@test.com', 'password123');

        // The admin firewall uses form-login (cookie-based), not JWT.
        // A JWT in the Authorization header is meaningless here.
        // Access control requires ROLE_ADMIN; unauthenticated users are redirected to admin_login (302).
        $this->browser->request(
            'GET',
            '/admin',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $statusCode = $this->getLastStatusCode();
        $this->assertNotSame(200, $statusCode, 'A player JWT must not grant access to the admin panel.');
    }

    // -------------------------------------------------------------------------
    // 9. Guest flow remains unbroken
    // -------------------------------------------------------------------------

    public function testGuestCanCreateAndPlayWithoutAuth(): void
    {
        // POST /api/game without auth
        $this->browser->request(
            'POST',
            '/api/game',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{}'
        );
        $this->assertSame(201, $this->getLastStatusCode());
        $gameData = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $gameId   = $gameData['id'];

        // GET /api/game/{id} without auth
        $this->browser->request('GET', '/api/game/' . $gameId);
        $this->assertSame(200, $this->getLastStatusCode());

        // POST /api/game/{id}/prologue without auth
        $this->browser->request(
            'POST',
            '/api/game/' . $gameId . '/prologue',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            \json_encode([
                'character_name'        => 'Guest Librarian',
                'character_description' => 'A wandering archivist.',
                'genre'                 => 'Fantasía',
                'epoch'                 => 'Medieval',
            ], \JSON_THROW_ON_ERROR)
        );
        $this->assertSame(200, $this->getLastStatusCode());
    }
}
