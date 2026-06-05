<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Entity\Attribute;
use App\Entity\GameSession;
use App\Entity\User;
use App\Enum\AttributeType;
use App\Enum\GamePhase;
use App\Service\GameEngine;
use App\Tests\Behat\Dice\QueuedRandomGenerator;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Context for JSON REST API testing via Symfony's in-process HttpKernelBrowser.
 *
 * The FriendsOfBehat\SymfonyExtension wires the kernel and all services
 * through Symfony's DI container — no manual boot needed.
 *
 * Two layers of steps live here:
 *  - low-level HTTP/JSON steps ("I send a ... request", "the response status ...")
 *  - domain steps for the game rules ("a game positioned at phase ...",
 *    "the next action roll is a hit", "the :attr attribute background is ...").
 * The domain steps arrange state through GameEngine and act through the real API,
 * so the scenarios exercise the actual endpoints while reading like the rulebook.
 */
final class FeatureContext implements Context
{
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $browser;

    /** @var array<string, mixed>|null */
    private ?array $responseData = null;

    /** JWT bearer token injected into every request once a player is authenticated. */
    private ?string $authToken = null;

    /** Owner used for games created during the scenario. */
    private ?User $currentUser = null;

    /** UUID of the game under test, used to build endpoint URLs and refetch state. */
    private ?Uuid $gameUuid = null;

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly EntityManagerInterface $entityManager,
        private readonly GameEngine $gameEngine,
        private readonly QueuedRandomGenerator $dice,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        // Create a KernelBrowser that talks directly to the Symfony kernel (no HTTP server needed).
        // Reboot is disabled so the dice queue (a shared service) survives across requests.
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
        $this->authToken   = null;
        $this->currentUser = null;
        $this->gameUuid    = null;
        $this->responseData = null;
    }

    // -------------------------------------------------------------------------
    // Database reset
    // -------------------------------------------------------------------------

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
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        $this->entityManager->clear();
    }

    // -------------------------------------------------------------------------
    // Request step definitions
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
    // Response assertion step definitions
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
        $this->getDecodedResponse(); // throws if invalid
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
            throw new \RuntimeException(\sprintf(
                'Expected key "%s" to equal "%s" but got "%s".',
                $key,
                $value,
                $actual,
            ));
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
            throw new \RuntimeException(\sprintf(
                'Expected key "%s" to contain "%s" but got "%s".',
                $key,
                $value,
                (string) $data[$key],
            ));
        }
    }

    // -------------------------------------------------------------------------
    // Domain: arrange (set up state through GameEngine / Doctrine)
    // -------------------------------------------------------------------------

    /**
     * Creates a persisted ROLE_PLAYER user and authenticates as them by issuing a
     * JWT directly (no login round-trip, so scenarios avoid the login rate limiter).
     *
     * @Given I am an authenticated player
     */
    public function iAmAnAuthenticatedPlayer(): void
    {
        $user = new User();
        $user->setEmail('player@biblioteca.test');
        $user->setRoles([User::ROLE_PLAYER]);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'irrelevant-for-jwt'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->currentUser = $user;
        $this->authToken   = $this->jwtManager->create($user);
    }

    /**
     * @Given a new game
     */
    public function aNewGame(): void
    {
        $game = $this->gameEngine->createGame('aventura_rapida', $this->currentUser);
        $this->gameUuid = $game->getId();
    }

    /**
     * Positions a fresh game directly at the requested phase. The prologue is
     * completed (so character data exists and we leave PROLOGUE), then the phase
     * is set straight to the target — scenarios add their own rolls via When steps.
     *
     * @Given a game positioned at phase :phase
     */
    public function aGamePositionedAtPhase(string $phase): void
    {
        $target = GamePhase::from($phase);
        $game   = $this->gameEngine->createGame('aventura_rapida', $this->currentUser);
        $this->gameUuid = $game->getId();

        if ($target === GamePhase::PROLOGUE) {
            return;
        }

        // Leave the prologue with placeholder character data.
        $this->gameEngine->completePrologue($game, 'Test Hero', 'A test character.', 'Investigación', 'Contemporanea');

        if ($target !== GamePhase::CHAPTER_1) {
            $game->setCurrentPhase($target);
            $this->entityManager->flush();
        }
    }

    /**
     * @Given the :attr attribute has background :background and support :support
     */
    public function theAttributeHasBackgroundAndSupport(string $attr, int $background, int $support): void
    {
        $attribute = $this->getAttribute($attr);
        $attribute->setBackground($background);
        $attribute->setSupport($support);
        $this->entityManager->flush();
    }

    /**
     * @Given the game overcome score is :value
     */
    public function theGameOvercomeScoreIs(int $value): void
    {
        $game = $this->refetchGame();
        $game->setOvercomeScore($value);
        $this->entityManager->flush();
    }

    // -------------------------------------------------------------------------
    // Domain: dice control (queue deterministic die faces)
    // -------------------------------------------------------------------------

    /**
     * Queues the three faces consumed by an action roll, in engine order:
     * action die (d6), challenge die 1 (d10), challenge die 2 (d10).
     *
     * @Given the next action roll is action die :d6 and challenge dice :c1 and :c2
     */
    public function theNextActionRollIs(int $d6, int $c1, int $c2): void
    {
        $this->dice->push($d6, $c1, $c2);
    }

    /**
     * Readable shortcut for the common outcomes. The queued faces guarantee the
     * outcome for the small non-negative modifiers seen in early phases:
     *   hit      → score beats both minimum challenge dice
     *   weak_hit → score beats one die, loses the other
     *   miss     → score loses to both maximum challenge dice
     *
     * @Given the next action roll is a :outcome
     */
    public function theNextActionRollIsA(string $outcome): void
    {
        match ($outcome) {
            'hit'      => $this->dice->push(6, 1, 1),
            'weak_hit' => $this->dice->push(1, 1, 10),
            'miss'     => $this->dice->push(1, 10, 10),
            default    => throw new \InvalidArgumentException(\sprintf('Unknown outcome "%s".', $outcome)),
        };
    }

    /**
     * Queues the two d10 faces for the final roll (no action die is rolled;
     * action_score is the accumulated overcome score).
     *
     * @Given the next final roll challenge dice are :c1 and :c2
     */
    public function theNextFinalRollChallengeDiceAre(int $c1, int $c2): void
    {
        $this->dice->push($c1, $c2);
    }

    // -------------------------------------------------------------------------
    // Domain: act (exercise the real API endpoints)
    // -------------------------------------------------------------------------

    /**
     * @When I complete the prologue with name :name genre :genre epoch :epoch
     */
    public function iCompleteThePrologue(string $name, string $genre, string $epoch): void
    {
        $this->sendGameRequest('POST', '/prologue', [
            'character_name'        => $name,
            'character_description' => 'Written during the prologue.',
            'genre'                 => $genre,
            'epoch'                 => $epoch,
        ]);
    }

    /**
     * @When I complete the prologue without a name
     */
    public function iCompleteThePrologueWithoutAName(): void
    {
        $this->sendGameRequest('POST', '/prologue', [
            'character_description' => 'No name provided.',
            'genre'                 => 'Investigación',
            'epoch'                 => 'Contemporanea',
        ]);
    }

    /**
     * @When I generate the chapter book
     */
    public function iGenerateTheChapterBook(): void
    {
        $this->sendGameRequest('POST', '/chapter/book');
    }

    /**
     * @When I resolve the chapter using :attr
     */
    public function iResolveTheChapterUsing(string $attr): void
    {
        $this->sendGameRequest('POST', '/chapter/roll', ['attribute' => $attr]);
    }

    /**
     * @When I advance the chapter
     */
    public function iAdvanceTheChapter(): void
    {
        $this->sendGameRequest('POST', '/chapter/advance');
    }

    /**
     * @When I set the support title :title for :attr
     */
    public function iSetTheSupportTitleFor(string $title, string $attr): void
    {
        $this->sendGameRequest('POST', '/chapter/support-title', [
            'attribute'     => $attr,
            'support_title' => $title,
        ]);
    }

    /**
     * @When I generate the epilogue book
     */
    public function iGenerateTheEpilogueBook(): void
    {
        $this->sendGameRequest('POST', '/epilogue/book');
    }

    /**
     * @When I resolve the epilogue action using :attr
     */
    public function iResolveTheEpilogueActionUsing(string $attr): void
    {
        $this->sendGameRequest('POST', '/epilogue/action', ['attribute' => $attr]);
    }

    /**
     * @When I resolve the epilogue action using :attr with support :supportAttr
     */
    public function iResolveTheEpilogueActionUsingWithSupport(string $attr, string $supportAttr): void
    {
        $this->sendGameRequest('POST', '/epilogue/action', [
            'attribute'         => $attr,
            'support_attribute' => $supportAttr,
        ]);
    }

    /**
     * @When I roll the final outcome
     */
    public function iRollTheFinalOutcome(): void
    {
        $this->sendGameRequest('POST', '/epilogue/final');
    }

    // -------------------------------------------------------------------------
    // Domain: assert (read the rules back from persisted state / responses)
    // -------------------------------------------------------------------------

    /**
     * @Then the :attr attribute background is :value
     */
    public function theAttributeBackgroundIs(string $attr, int $value): void
    {
        $actual = $this->refetchAttribute($attr)->getBackground();
        $this->assertSame($value, $actual, \sprintf('%s background', $attr));
    }

    /**
     * @Then the :attr attribute support is :value
     */
    public function theAttributeSupportIs(string $attr, int $value): void
    {
        $actual = $this->refetchAttribute($attr)->getSupport();
        $this->assertSame($value, $actual, \sprintf('%s support', $attr));
    }

    /**
     * @Then the :attr attribute support title is :title
     */
    public function theAttributeSupportTitleIs(string $attr, string $title): void
    {
        $actual = (string) $this->refetchAttribute($attr)->getSupportTitle();
        if ($actual !== $title) {
            throw new \RuntimeException(\sprintf('Expected %s support title "%s" but got "%s".', $attr, $title, $actual));
        }
    }

    /**
     * @Then the overcome score is :value
     */
    public function theOvercomeScoreIs(int $value): void
    {
        $this->assertSame($value, $this->refetchGame()->getOvercomeScore(), 'overcome score');
    }

    /**
     * @Then the game phase is :phase
     */
    public function theGamePhaseIs(string $phase): void
    {
        $actual = $this->refetchGame()->getCurrentPhase()->value;
        if ($actual !== $phase) {
            throw new \RuntimeException(\sprintf('Expected game phase "%s" but got "%s".', $phase, $actual));
        }
    }

    /**
     * @Then support has been used
     */
    public function supportHasBeenUsed(): void
    {
        if (!$this->refetchGame()->isSupportUsed()) {
            throw new \RuntimeException('Expected support to be marked as used, but it was not.');
        }
    }

    /**
     * @Then support has not been used
     */
    public function supportHasNotBeenUsed(): void
    {
        if ($this->refetchGame()->isSupportUsed()) {
            throw new \RuntimeException('Expected support to be unused, but it was marked as used.');
        }
    }

    /**
     * Reads the outcome from a roll response shaped { roll_result: { outcome }, game }.
     *
     * @Then the roll outcome is :outcome
     */
    public function theRollOutcomeIs(string $outcome): void
    {
        $data = $this->getDecodedResponse();

        $actual = $data['roll_result']['outcome'] ?? null;
        if ($actual !== $outcome) {
            throw new \RuntimeException(\sprintf(
                'Expected roll outcome "%s" but got "%s".',
                $outcome,
                \is_string($actual) ? $actual : \var_export($actual, true),
            ));
        }
    }

    /**
     * Reads the applied modifier from a roll response, proving the modifier math
     * (base + background + support, plus any epilogue support bonus).
     *
     * @Then the roll modifier is :value
     */
    public function theRollModifierIs(int $value): void
    {
        $data   = $this->getDecodedResponse();
        $actual = $data['roll_result']['modifier'] ?? null;
        if ($actual !== $value) {
            throw new \RuntimeException(\sprintf(
                'Expected roll modifier %d but got %s.',
                $value,
                \var_export($actual, true),
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
    // Internals
    // -------------------------------------------------------------------------

    private function sendGameRequest(string $method, string $path, ?array $body = null): void
    {
        $url = \sprintf('/api/game/%s%s', (string) $this->gameUuid, $path);
        $this->sendRequest($method, $url, $body === null ? null : \json_encode($body, \JSON_THROW_ON_ERROR));
    }

    private function getAttribute(string $type): Attribute
    {
        $game = $this->refetchGame();
        foreach ($game->getAttributes() as $attribute) {
            if ($attribute->getType() === AttributeType::from($type)) {
                return $attribute;
            }
        }

        throw new \RuntimeException(\sprintf('Attribute "%s" not found on the current game.', $type));
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

    private function assertSame(int $expected, int $actual, string $label): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(\sprintf('Expected %s to be %d but got %d.', $label, $expected, $actual));
        }
    }

    private function sendRequest(string $method, string $url, ?string $jsonBody): void
    {
        $this->responseData = null;

        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        if ($this->authToken !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->authToken;
        }

        $this->browser->request(
            \strtoupper($method),
            $url,
            [],
            [],
            $server,
            $jsonBody,
        );
    }

    /**
     * @return array<string, mixed>
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

        $this->responseData = $data;

        return $this->responseData;
    }
}
