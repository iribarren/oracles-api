<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Entity\Attribute;
use App\Entity\GameSession;
use App\Entity\User;
use App\Enum\AttributeType;
use App\Service\GameEngine;
use App\Tests\Behat\Dice\QueuedRandomGenerator;
use App\Tests\Behat\Steps\AuthSteps;
use App\Tests\Behat\Steps\ExportHealthSteps;
use App\Tests\Behat\Steps\GameSteps;
use App\Tests\Behat\Steps\JournalSteps;
use App\Tests\Behat\Steps\OracleSteps;
use App\Tests\Behat\Steps\OwnershipSteps;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Behat context for JSON REST API testing via Symfony's in-process HttpKernelBrowser.
 *
 * This host class holds the shared infrastructure — the browser, request/response
 * plumbing, identity management, per-scenario resets and generic HTTP/JSON steps.
 * The domain vocabularies live in traits (one per area) so the step library stays
 * readable as it grows:
 *
 *   GameSteps · AuthSteps · OwnershipSteps · OracleSteps · JournalSteps · ExportHealthSteps
 *
 * All traits are flattened into this single class, so they share state through
 * `$this` with no cross-context wiring needed.
 */
final class FeatureContext implements Context
{
    use GameSteps;
    use AuthSteps;
    use OwnershipSteps;
    use OracleSteps;
    use JournalSteps;
    use ExportHealthSteps;

    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $browser;

    /** @var array<string, mixed>|null */
    private ?array $responseData = null;

    /** JWT bearer token injected into every request for the active identity (null = guest). */
    private ?string $authToken = null;

    /** The primary authenticated player and their identity. */
    private ?User $currentUser = null;
    private ?string $currentUserEmail = null;

    /** Token of the game owner (primary player) and of a secondary player, for ownership scenarios. */
    private ?string $ownerToken = null;
    private ?string $otherPlayerToken = null;

    /** UUID of the game under test, used to build endpoint URLs and refetch state. */
    private ?Uuid $gameUuid = null;

    /** Book ids remembered for journal-link scenarios. */
    private ?int $lastBookId = null;
    private ?int $foreignBookId = null;

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly EntityManagerInterface $entityManager,
        private readonly GameEngine $gameEngine,
        private readonly QueuedRandomGenerator $dice,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly CacheItemPoolInterface $rateLimiterCache,
    ) {
        // Talk directly to the Symfony kernel (no HTTP server). Reboot is disabled so
        // the shared dice queue survives across requests within a scenario.
        $this->browser = new \Symfony\Bundle\FrameworkBundle\KernelBrowser($this->kernel);
        $this->browser->disableReboot();
    }

    /**
     * @BeforeScenario
     */
    public function resetScenarioState(): void
    {
        $this->dice->reset();
        $this->dice->enableStrictMode();

        $this->authToken        = null;
        $this->currentUser      = null;
        $this->currentUserEmail = null;
        $this->ownerToken       = null;
        $this->otherPlayerToken = null;
        $this->gameUuid         = null;
        $this->lastBookId       = null;
        $this->foreignBookId    = null;
        $this->responseData     = null;

        // Login throttling persists in the filesystem cache pool; clear it so failed
        // logins in one scenario don't trip the limiter (429) in the next.
        $this->rateLimiterCache->clear();
    }

    /**
     * @BeforeScenario
     */
    public function resetDatabase(): void
    {
        $conn = $this->entityManager->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $conn->executeStatement('DELETE FROM refresh_tokens');
        $conn->executeStatement('DELETE FROM journal_entries');
        $conn->executeStatement('DELETE FROM roll_results');
        $conn->executeStatement('DELETE FROM books');
        $conn->executeStatement('DELETE FROM attributes');
        $conn->executeStatement('DELETE FROM game_sessions');
        $conn->executeStatement('DELETE FROM users');
        // Oracle tables start empty so the API serves the fallback constants; scenarios
        // that test the DB-first path seed them explicitly.
        $conn->executeStatement('DELETE FROM oracle_options');
        $conn->executeStatement('DELETE FROM oracle_categories');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        $this->entityManager->clear();
    }

    // -------------------------------------------------------------------------
    // Identity (shared by game, auth and ownership scenarios)
    // -------------------------------------------------------------------------

    /**
     * Creates a persisted ROLE_PLAYER user and authenticates as them by issuing a
     * JWT directly (no login round-trip, so most scenarios avoid the login limiter).
     *
     * @Given I am an authenticated player
     */
    public function iAmAnAuthenticatedPlayer(): void
    {
        $this->currentUser      = $this->createPlayer('player@biblioteca.test');
        $this->currentUserEmail = 'player@biblioteca.test';
        $this->authToken        = $this->tokenFor($this->currentUser);
        $this->ownerToken       = $this->authToken;
    }

    // -------------------------------------------------------------------------
    // Generic request steps
    // -------------------------------------------------------------------------

    /**
     * @When I send a :method request to :url
     */
    public function iSendARequestTo(string $method, string $url): void
    {
        $this->sendRequest($method, $url, null);
    }

    /**
     * @When I send a :method request to :url with body:
     */
    public function iSendARequestToWithBody(string $method, string $url, PyStringNode $body): void
    {
        $this->sendRequest($method, $url, $body->getRaw());
    }

    /**
     * @When I send a :method request to :url with JSON:
     */
    public function iSendARequestToWithJson(string $method, string $url, PyStringNode $body): void
    {
        $this->sendRequest($method, $url, $body->getRaw());
    }

    // -------------------------------------------------------------------------
    // Generic response assertions
    // -------------------------------------------------------------------------

    /**
     * @Then the response status code should be :code
     */
    public function theResponseStatusCodeShouldBe(int $code): void
    {
        $actual = $this->browser->getResponse()->getStatusCode();
        if ($actual !== $code) {
            throw new \RuntimeException(\sprintf(
                'Expected HTTP %d but got %d. Response body: %s',
                $code,
                $actual,
                $this->browser->getResponse()->getContent(),
            ));
        }
    }

    /**
     * @Then the response should be valid JSON
     */
    public function theResponseShouldBeValidJson(): void
    {
        $this->getDecodedResponse();
    }

    /**
     * @Then the JSON response should have key :key
     */
    public function theJsonResponseShouldHaveKey(string $key): void
    {
        $data = $this->getDecodedResponse();
        if (!\array_key_exists($key, $data)) {
            throw new \RuntimeException(\sprintf(
                'Key "%s" not found in response. Available keys: %s',
                $key,
                \implode(', ', \array_keys($data)),
            ));
        }
    }

    /**
     * @Then the JSON response key :key should equal :value
     */
    public function theJsonResponseKeyShouldEqual(string $key, string $value): void
    {
        $data = $this->getDecodedResponse();
        if (!\array_key_exists($key, $data)) {
            throw new \RuntimeException(\sprintf('Key "%s" not found in JSON response.', $key));
        }
        $actual = (string) $data[$key];
        if ($actual !== $value) {
            throw new \RuntimeException(\sprintf('Expected key "%s" to equal "%s" but got "%s".', $key, $value, $actual));
        }
    }

    /**
     * @Then the JSON response key :key should contain :value
     */
    public function theJsonResponseKeyShouldContain(string $key, string $value): void
    {
        $data = $this->getDecodedResponse();
        if (!\array_key_exists($key, $data)) {
            throw new \RuntimeException(\sprintf('Key "%s" not found in JSON response.', $key));
        }
        if (!\str_contains((string) $data[$key], $value)) {
            throw new \RuntimeException(\sprintf('Expected key "%s" to contain "%s" but got "%s".', $key, $value, (string) $data[$key]));
        }
    }

    /**
     * Asserts a dot-notation path into the decoded JSON (e.g. "checks.database.status").
     *
     * @Then the JSON path :path should equal :value
     */
    public function theJsonPathShouldEqual(string $path, string $value): void
    {
        $actual = $this->getJsonPath($path);
        if ((string) $actual !== $value) {
            throw new \RuntimeException(\sprintf(
                'Expected JSON path "%s" to equal "%s" but got "%s".',
                $path,
                $value,
                \is_scalar($actual) ? (string) $actual : \var_export($actual, true),
            ));
        }
    }

    /**
     * @Then the request is rejected
     */
    public function theRequestIsRejected(): void
    {
        $status = $this->browser->getResponse()->getStatusCode();
        if ($status < 400) {
            throw new \RuntimeException(\sprintf('Expected the request to be rejected (>=400) but got %d.', $status));
        }
    }

    // -------------------------------------------------------------------------
    // Shared helpers (used by the step traits)
    // -------------------------------------------------------------------------

    private function createPlayer(string $email): User
    {
        return $this->createPlayerWithPassword($email, 'password123');
    }

    private function createPlayerWithPassword(string $email, string $password): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setRoles([User::ROLE_PLAYER]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function tokenFor(User $user): string
    {
        return $this->jwtManager->create($user);
    }

    private function actAs(?string $token): void
    {
        $this->authToken = $token;
    }

    private function sendGameRequest(string $method, string $path, ?array $body = null): void
    {
        $url = \sprintf('/api/game/%s%s', (string) $this->gameUuid, $path);
        $this->sendRequest($method, $url, $body === null ? null : \json_encode($body, \JSON_THROW_ON_ERROR));
    }

    private function sendRequest(string $method, string $url, ?string $jsonBody): void
    {
        $this->responseData = null;

        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($this->authToken !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->authToken;
        }

        $this->browser->request(\strtoupper($method), $url, [], [], $server, $jsonBody);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function getDecodedResponse(): array
    {
        if ($this->responseData !== null) {
            return $this->responseData;
        }

        $content = $this->browser->getResponse()->getContent();
        if ($content === false || $content === '') {
            throw new \RuntimeException('Response body is empty.');
        }

        $data = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($data)) {
            throw new \RuntimeException(\sprintf('Response is not a JSON object/array. Got: %s', $content));
        }

        return $this->responseData = $data;
    }

    private function getJsonPath(string $path): mixed
    {
        $value = $this->getDecodedResponse();
        foreach (\explode('.', $path) as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                throw new \RuntimeException(\sprintf('JSON path "%s" not found (missing segment "%s").', $path, $segment));
            }
            $value = $value[$segment];
        }

        return $value;
    }

    private function refetchGame(): GameSession
    {
        // Clear the identity map so we read the state the API just persisted.
        $this->entityManager->clear();

        $game = $this->entityManager->getRepository(GameSession::class)->find($this->gameUuid);
        if ($game === null) {
            throw new \RuntimeException('Game under test not found in the database.');
        }

        return $game;
    }

    private function getAttribute(string $type): Attribute
    {
        return $this->refetchAttribute($type);
    }

    private function refetchAttribute(string $type): Attribute
    {
        $target = AttributeType::from($type);
        foreach ($this->refetchGame()->getAttributes() as $attribute) {
            if ($attribute->getType() === $target) {
                return $attribute;
            }
        }

        throw new \RuntimeException(\sprintf('Attribute "%s" not found on the current game.', $type));
    }

    private function assertSameInt(int $expected, int $actual, string $label): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(\sprintf('Expected %s to be %d but got %d.', $label, $expected, $actual));
        }
    }
}
