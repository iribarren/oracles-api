<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integration tests for the OpenAPI documentation endpoints.
 *
 * These are read-only GET requests — no database interaction or tearDown needed.
 */
class OpenApiDocTest extends WebTestCase
{
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = static::createClient();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function getJson(string $url): array
    {
        $this->browser->request('GET', $url);
        $response = $this->browser->getResponse();
        return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function getLastStatusCode(): int
    {
        return $this->browser->getResponse()->getStatusCode();
    }

    private function getLastContentType(): string
    {
        return $this->browser->getResponse()->headers->get('Content-Type') ?? '';
    }

    // -------------------------------------------------------------------------
    // GET /api/doc/  — SwaggerUI HTML (public area, no auth required)
    // -------------------------------------------------------------------------

    public function testSwaggerUiReturns200(): void
    {
        $this->browser->request('GET', '/api/doc/');
        $this->assertSame(200, $this->getLastStatusCode());
    }

    public function testSwaggerUiResponseBodyContainsSwaggerKeyword(): void
    {
        $this->browser->request('GET', '/api/doc/');
        $body = $this->browser->getResponse()->getContent();
        $this->assertStringContainsStringIgnoringCase('swagger', $body);
    }

    // -------------------------------------------------------------------------
    // GET /api/doc.json  — OpenAPI JSON spec (public area, no auth required)
    // -------------------------------------------------------------------------

    public function testJsonSpecReturns200(): void
    {
        $this->browser->request('GET', '/api/doc.json');
        $this->assertSame(200, $this->getLastStatusCode());
    }

    public function testJsonSpecContentTypeIsJson(): void
    {
        $this->browser->request('GET', '/api/doc.json');
        $this->assertStringContainsString('application/json', $this->getLastContentType());
    }

    public function testJsonSpecBodyIsValidJson(): void
    {
        $this->browser->request('GET', '/api/doc.json');
        $body = $this->browser->getResponse()->getContent();
        $decoded = json_decode($body, true);
        $this->assertNotNull($decoded, 'Response body must be valid JSON');
    }

    public function testJsonSpecHasOpenApiKey(): void
    {
        $spec = $this->getJson('/api/doc.json');
        $this->assertArrayHasKey('openapi', $spec);
    }

    public function testJsonSpecOpenApiVersionStartsWith30(): void
    {
        $spec = $this->getJson('/api/doc.json');
        $this->assertStringStartsWith('3.0.', $spec['openapi']);
    }

    public function testJsonSpecHasInfoKey(): void
    {
        $spec = $this->getJson('/api/doc.json');
        $this->assertArrayHasKey('info', $spec);
    }

    public function testJsonSpecInfoTitleIsLaBiblioteca(): void
    {
        $spec = $this->getJson('/api/doc.json');
        $this->assertSame('La Biblioteca — Oracles API', $spec['info']['title']);
    }

    public function testJsonSpecHasPathsKey(): void
    {
        $spec = $this->getJson('/api/doc.json');
        $this->assertArrayHasKey('paths', $spec);
    }

    /**
     * The public spec only exposes public endpoints:
     * health, test, auth/login, auth/register, auth/refresh,
     * oracle/tables, oracle/random-setting = 7 paths.
     */
    public function testJsonSpecPathsOnlyContainsPublicEndpoints(): void
    {
        $spec = $this->getJson('/api/doc.json');
        $paths = array_keys($spec['paths']);

        $allowedPrefixes = ['/api/health', '/api/test', '/api/auth/login', '/api/auth/register', '/api/auth/refresh', '/api/oracle/tables', '/api/oracle/random-setting'];

        foreach ($paths as $path) {
            $isAllowed = false;
            foreach ($allowedPrefixes as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    $isAllowed = true;
                    break;
                }
            }
            $this->assertTrue($isAllowed, "Path '{$path}' should not appear in the public API doc");
        }
    }

    public function testJsonSpecPublicPathsArePresent(): void
    {
        $spec = $this->getJson('/api/doc.json');
        $paths = array_keys($spec['paths']);

        $this->assertContains('/api/oracle/tables', $paths);
        $this->assertContains('/api/oracle/random-setting', $paths);
    }

    public function testJsonSpecDoesNotExposeAuthenticatedEndpoints(): void
    {
        $spec = $this->getJson('/api/doc.json');
        $paths = array_keys($spec['paths']);

        $this->assertNotContains('/api/game', $paths);
        $this->assertNotContains('/api/games', $paths);
        $this->assertNotContains('/api/player/sessions', $paths);
        $this->assertNotContains('/api/auth/me', $paths);
    }

    public function testJsonSpecHasComponentsKey(): void
    {
        $spec = $this->getJson('/api/doc.json');
        $this->assertArrayHasKey('components', $spec);
    }
}
