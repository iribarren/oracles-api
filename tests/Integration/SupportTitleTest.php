<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Attribute;
use App\Entity\User;
use App\Enum\AttributeType;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Integration tests for POST /api/game/{id}/chapter/support-title.
 *
 * Each test sets up its own game state by calling the API flow up to the chapter
 * phase and then directly manipulating attribute support values via Doctrine so
 * that the endpoint can be exercised without relying on dice randomness.
 */
class SupportTitleTest extends WebTestCase
{
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $browser;
    private EntityManagerInterface $em;
    private string $token;

    protected function setUp(): void
    {
        $this->browser = static::createClient();
        $this->em      = static::getContainer()->get(EntityManagerInterface::class);

        $this->purgeDatabase();

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user   = new User();
        $user->setEmail('player@test.com');
        $user->setRoles([User::ROLE_PLAYER]);
        $user->setPassword($hasher->hashPassword($user, 'password'));
        $this->em->persist($user);
        $this->em->flush();

        $jwtManager  = static::getContainer()->get(JWTTokenManagerInterface::class);
        $this->token = $jwtManager->create($user);
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

    /**
     * @return array<string, mixed>
     */
    private function postJson(string $url, array $body = []): array
    {
        $this->browser->request(
            'POST',
            $url,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token],
            json_encode($body, JSON_THROW_ON_ERROR)
        );

        $response = $this->browser->getResponse();
        return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function getLastStatusCode(): int
    {
        return $this->browser->getResponse()->getStatusCode();
    }

    /**
     * Creates a game via the API and returns the full response body.
     *
     * @return array<string, mixed>
     */
    private function createGame(): array
    {
        $data = $this->postJson('/api/game');
        $this->assertSame(201, $this->getLastStatusCode());
        return $data;
    }

    /**
     * Completes the prologue with default fixture data so the game enters chapter_1.
     *
     * @return array<string, mixed>
     */
    private function completePrologue(string $gameId): array
    {
        $data = $this->postJson("/api/game/{$gameId}/prologue", [
            'character_name'        => 'Librarian',
            'character_description' => 'A keeper of ancient tomes.',
            'genre'                 => 'Fantasía',
            'epoch'                 => 'Medieval',
        ]);
        $this->assertSame(200, $this->getLastStatusCode());
        return $data;
    }

    /**
     * Directly sets the support value of a named attribute on a game session via
     * Doctrine, bypassing the dice roll. Calls clear() after flushing so that
     * subsequent HTTP requests through the kernel pick up the fresh DB state.
     */
    private function setAttributeSupport(string $gameId, AttributeType $type, int $support): void
    {
        // Re-fetch the game session entity from the database
        $gameRepo = $this->em->getRepository(\App\Entity\GameSession::class);
        $game     = $gameRepo->findOneBy(['id' => \Symfony\Component\Uid\Uuid::fromString($gameId)]);

        $this->assertNotNull($game, 'Game session must exist before manipulating attributes');

        /** @var Attribute[] $attributes */
        $attributes = $this->em->getRepository(Attribute::class)->findBy(['game_session' => $game]);

        foreach ($attributes as $attribute) {
            if ($attribute->getType() === $type) {
                $attribute->setSupport($support);
                $this->em->flush();
                $this->em->clear();
                return;
            }
        }

        $this->fail(\sprintf('Attribute of type "%s" not found for game %s', $type->value, $gameId));
    }

    // -------------------------------------------------------------------------
    // POST /api/game/{id}/chapter/support-title
    // -------------------------------------------------------------------------

    /**
     * Happy path: when body has support=1, saving a title should return 200 and
     * include the title in the attributes array of the full game state response.
     */
    public function testSaveSupportTitleReturns200WhenAttributeHasSupport(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);

        $this->setAttributeSupport($game['id'], AttributeType::BODY, 1);

        $this->postJson("/api/game/{$game['id']}/chapter/support-title", [
            'attribute'     => 'body',
            'support_title' => 'Ancient map',
        ]);

        $this->assertSame(200, $this->getLastStatusCode());
    }

    /**
     * Happy path: the response must include the saved support_title on the body attribute.
     */
    public function testSaveSupportTitleStoresTitleInResponse(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);

        $this->setAttributeSupport($game['id'], AttributeType::BODY, 1);

        $response = $this->postJson("/api/game/{$game['id']}/chapter/support-title", [
            'attribute'     => 'body',
            'support_title' => 'Ancient map',
        ]);

        $this->assertArrayHasKey('attributes', $response);

        $bodyAttribute = null;
        foreach ($response['attributes'] as $attr) {
            if ($attr['type'] === 'body') {
                $bodyAttribute = $attr;
                break;
            }
        }

        $this->assertNotNull($bodyAttribute, 'Body attribute must be present in the response');
        $this->assertSame('Ancient map', $bodyAttribute['support_title']);
    }

    /**
     * The response is the full game state and must contain the standard top-level keys.
     */
    public function testSaveSupportTitleResponseContainsFullGameState(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);

        $this->setAttributeSupport($game['id'], AttributeType::BODY, 1);

        $response = $this->postJson("/api/game/{$game['id']}/chapter/support-title", [
            'attribute'     => 'body',
            'support_title' => 'Ancient map',
        ]);

        $this->assertArrayHasKey('id',            $response);
        $this->assertArrayHasKey('current_phase', $response);
        $this->assertArrayHasKey('attributes',    $response);
    }

    /**
     * HTML tags in the title should be stripped (strip_tags) before persisting.
     * The saved value should contain the plain text only.
     */
    public function testSaveSupportTitleStripsHtmlTags(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);

        $this->setAttributeSupport($game['id'], AttributeType::MIND, 1);

        $response = $this->postJson("/api/game/{$game['id']}/chapter/support-title", [
            'attribute'     => 'mind',
            'support_title' => '<b>Old scroll</b>',
        ]);

        $this->assertSame(200, $this->getLastStatusCode());

        $mindAttribute = null;
        foreach ($response['attributes'] as $attr) {
            if ($attr['type'] === 'mind') {
                $mindAttribute = $attr;
                break;
            }
        }

        $this->assertNotNull($mindAttribute);
        $this->assertStringNotContainsString('<b>', $mindAttribute['support_title']);
        $this->assertStringContainsString('Old scroll', $mindAttribute['support_title']);
    }

    // -------------------------------------------------------------------------
    // Validation — support_title
    // -------------------------------------------------------------------------

    /**
     * An empty support_title must be rejected with 422.
     */
    public function testEmptySupportTitleReturns422(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);

        $this->setAttributeSupport($game['id'], AttributeType::BODY, 1);

        $this->postJson("/api/game/{$game['id']}/chapter/support-title", [
            'attribute'     => 'body',
            'support_title' => '',
        ]);

        $this->assertSame(422, $this->getLastStatusCode());
    }

    /**
     * A support_title that consists only of whitespace must also be rejected with 422
     * because trim() will reduce it to an empty string.
     */
    public function testWhitespaceSupportTitleReturns422(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);

        $this->setAttributeSupport($game['id'], AttributeType::BODY, 1);

        $this->postJson("/api/game/{$game['id']}/chapter/support-title", [
            'attribute'     => 'body',
            'support_title' => '   ',
        ]);

        $this->assertSame(422, $this->getLastStatusCode());
    }

    /**
     * A support_title with exactly 51 characters must be rejected with 422.
     */
    public function testTooLongSupportTitleReturns422(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);

        $this->setAttributeSupport($game['id'], AttributeType::BODY, 1);

        $this->postJson("/api/game/{$game['id']}/chapter/support-title", [
            'attribute'     => 'body',
            'support_title' => str_repeat('a', 51),
        ]);

        $this->assertSame(422, $this->getLastStatusCode());
    }

    /**
     * A support_title with exactly 50 characters must be accepted with 200.
     */
    public function testMaxLengthSupportTitleReturns200(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);

        $this->setAttributeSupport($game['id'], AttributeType::BODY, 1);

        $this->postJson("/api/game/{$game['id']}/chapter/support-title", [
            'attribute'     => 'body',
            'support_title' => str_repeat('a', 50),
        ]);

        $this->assertSame(200, $this->getLastStatusCode());
    }

    /**
     * The validation response for an empty title must include a 'details' key.
     */
    public function testEmptySupportTitleResponseHasDetailsKey(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);

        $this->setAttributeSupport($game['id'], AttributeType::BODY, 1);

        $data = $this->postJson("/api/game/{$game['id']}/chapter/support-title", [
            'attribute'     => 'body',
            'support_title' => '',
        ]);

        $this->assertArrayHasKey('details', $data);
    }

    // -------------------------------------------------------------------------
    // Validation — attribute field
    // -------------------------------------------------------------------------

    /**
     * An unrecognised attribute value must be rejected with 422.
     */
    public function testInvalidAttributeValueReturns422(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);

        $this->postJson("/api/game/{$game['id']}/chapter/support-title", [
            'attribute'     => 'invalid',
            'support_title' => 'test',
        ]);

        $this->assertSame(422, $this->getLastStatusCode());
    }

    /**
     * The validation response for an invalid attribute must include a 'details' key.
     */
    public function testInvalidAttributeResponseHasDetailsKey(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);

        $data = $this->postJson("/api/game/{$game['id']}/chapter/support-title", [
            'attribute'     => 'invalid',
            'support_title' => 'test',
        ]);

        $this->assertArrayHasKey('details', $data);
    }

    // -------------------------------------------------------------------------
    // Business rule — attribute has no support points
    // -------------------------------------------------------------------------

    /**
     * When the targeted attribute has support=0 the GameEngine throws a LogicException,
     * which the controller maps to 400.
     */
    public function testAttributeWithNoSupportReturns400(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);

        // mind starts at support=0; do not manipulate it so it stays at 0
        $this->postJson("/api/game/{$game['id']}/chapter/support-title", [
            'attribute'     => 'mind',
            'support_title' => 'Old scroll',
        ]);

        $this->assertSame(400, $this->getLastStatusCode());
    }

    /**
     * The 400 response for missing support must contain an 'error' key.
     */
    public function testAttributeWithNoSupportResponseHasErrorKey(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);

        $data = $this->postJson("/api/game/{$game['id']}/chapter/support-title", [
            'attribute'     => 'mind',
            'support_title' => 'Old scroll',
        ]);

        $this->assertArrayHasKey('error', $data);
    }

    // -------------------------------------------------------------------------
    // Not found
    // -------------------------------------------------------------------------

    /**
     * A request to a non-existent game UUID must return 404.
     */
    public function testNonExistentGameReturns404(): void
    {
        $this->postJson('/api/game/00000000-0000-0000-0000-000000000000/chapter/support-title', [
            'attribute'     => 'body',
            'support_title' => 'Ancient map',
        ]);

        $this->assertSame(404, $this->getLastStatusCode());
    }

    /**
     * The 404 response must contain an 'error' key.
     */
    public function testNonExistentGameResponseHasErrorKey(): void
    {
        $data = $this->postJson('/api/game/00000000-0000-0000-0000-000000000000/chapter/support-title', [
            'attribute'     => 'body',
            'support_title' => 'Ancient map',
        ]);

        $this->assertArrayHasKey('error', $data);
    }
}
