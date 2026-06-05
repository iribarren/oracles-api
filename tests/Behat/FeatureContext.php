<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Base context for JSON REST API testing via Symfony's in-process HttpKernelBrowser.
 *
 * The FriendsOfBehat\SymfonyExtension wires the kernel and all services
 * through Symfony's DI container — no manual boot needed.
 */
final class FeatureContext implements Context
{
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $browser;

    /** @var array<string, mixed>|null */
    private ?array $responseData = null;

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly EntityManagerInterface $entityManager,
    ) {
        // Create a KernelBrowser that talks directly to the Symfony kernel (no HTTP server needed)
        $this->browser = new \Symfony\Bundle\FrameworkBundle\KernelBrowser($this->kernel);
        $this->browser->disableReboot();
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
    // Internals
    // -------------------------------------------------------------------------

    private function sendRequest(string $method, string $url, ?string $jsonBody): void
    {
        $this->responseData = null;

        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

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
