<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class OwnershipTest extends WebTestCase
{
    private KernelBrowser $browser;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->browser = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
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

    private function getGame(string $id, ?string $token = null): int
    {
        $headers = [];
        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $this->browser->request('GET', '/api/game/' . $id, [], [], $headers);

        return $this->browser->getResponse()->getStatusCode();
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    public function testGuestSessionIsAccessibleWithoutAuth(): void
    {
        $gameId = $this->createGame();
        $status = $this->getGame($gameId);

        $this->assertSame(200, $status);
    }

    public function testGuestSessionIsAccessibleWithAuth(): void
    {
        $this->createUser('player@test.com', 'password123');
        $token = $this->getJwt('player@test.com', 'password123');

        $gameId = $this->createGame();
        $status = $this->getGame($gameId, $token);

        $this->assertSame(200, $status);
    }

    public function testOwnedSessionIsAccessibleByOwner(): void
    {
        $this->createUser('owner@test.com', 'password123');
        $token = $this->getJwt('owner@test.com', 'password123');

        $gameId = $this->createGame($token);
        $status = $this->getGame($gameId, $token);

        $this->assertSame(200, $status);
    }

    public function testOwnedSessionReturns403ForDifferentUser(): void
    {
        $this->createUser('owner@test.com', 'password123');
        $this->createUser('other@test.com', 'password123');

        $ownerToken = $this->getJwt('owner@test.com', 'password123');
        $otherToken = $this->getJwt('other@test.com', 'password123');

        $gameId = $this->createGame($ownerToken);
        $status = $this->getGame($gameId, $otherToken);

        $this->assertSame(403, $status);
    }

    public function testOwnedSessionReturns403ForAnonymous(): void
    {
        $this->createUser('owner@test.com', 'password123');
        $token = $this->getJwt('owner@test.com', 'password123');

        $gameId = $this->createGame($token);
        $status = $this->getGame($gameId);

        $this->assertSame(403, $status);
    }

    public function testPlayerSessionsEndpointListsOnlyOwnSessions(): void
    {
        $this->createUser('usera@test.com', 'password123');
        $this->createUser('userb@test.com', 'password123');

        $tokenA = $this->getJwt('usera@test.com', 'password123');
        $tokenB = $this->getJwt('userb@test.com', 'password123');

        $this->createGame($tokenA);
        $this->createGame($tokenA);
        $this->createGame($tokenB);

        $this->browser->request('GET', '/api/player/sessions', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA,
        ]);
        $dataA = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(200, $this->browser->getResponse()->getStatusCode());
        $this->assertCount(2, $dataA);

        $this->browser->request('GET', '/api/player/sessions', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenB,
        ]);
        $dataB = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(200, $this->browser->getResponse()->getStatusCode());
        $this->assertCount(1, $dataB);
    }

    public function testPlayerSessionsEndpointRequiresAuth(): void
    {
        $this->browser->request('GET', '/api/player/sessions');

        $this->assertSame(401, $this->browser->getResponse()->getStatusCode());
    }
}
