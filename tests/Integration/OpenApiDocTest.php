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
    // GET /api/doc/  — SwaggerUI HTML
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
    // GET /api/doc.json  — OpenAPI JSON spec
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

    public function testJsonSpecPathsHasAtLeastTenEntries(): void
    {
        $spec = $this->getJson('/api/doc.json');
        $this->assertGreaterThanOrEqual(10, count($spec['paths']));
    }

    public function testJsonSpecHasComponentsKey(): void
    {
        $spec = $this->getJson('/api/doc.json');
        $this->assertArrayHasKey('components', $spec);
    }

    public function testJsonSpecComponentsHasSchemasKey(): void
    {
        $spec = $this->getJson('/api/doc.json');
        $this->assertArrayHasKey('schemas', $spec['components']);
    }

    public function testJsonSpecSchemasContainsGameSession(): void
    {
        $spec = $this->getJson('/api/doc.json');
        $this->assertArrayHasKey('GameSession', $spec['components']['schemas']);
    }

    public function testJsonSpecSchemasContainsAttribute(): void
    {
        $spec = $this->getJson('/api/doc.json');
        $this->assertArrayHasKey('Attribute', $spec['components']['schemas']);
    }

    public function testJsonSpecSchemasContainsBook(): void
    {
        $spec = $this->getJson('/api/doc.json');
        $this->assertArrayHasKey('Book', $spec['components']['schemas']);
    }

    public function testJsonSpecSchemasContainsRollResult(): void
    {
        $spec = $this->getJson('/api/doc.json');
        $this->assertArrayHasKey('RollResult', $spec['components']['schemas']);
    }

    public function testJsonSpecSchemasContainsJournalEntry(): void
    {
        $spec = $this->getJson('/api/doc.json');
        $this->assertArrayHasKey('JournalEntry', $spec['components']['schemas']);
    }

    public function testJsonSpecSchemasContainsError(): void
    {
        $spec = $this->getJson('/api/doc.json');
        $this->assertArrayHasKey('Error', $spec['components']['schemas']);
    }

    public function testJsonSpecSchemasContainsValidationError(): void
    {
        $spec = $this->getJson('/api/doc.json');
        $this->assertArrayHasKey('ValidationError', $spec['components']['schemas']);
    }
}
