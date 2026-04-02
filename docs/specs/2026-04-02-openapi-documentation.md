# Feature Spec: OpenAPI Documentation with SwaggerUI

**Date:** 2026-04-02
**Status:** Draft — pending approval
**Sub-project:** oracles-api (Symfony 7.2 backend)

---

## Overview

The Oracles API currently has 16 public endpoints across 4 controllers, but no machine-readable API specification or interactive documentation. This feature adds OpenAPI 3.0 documentation to every public endpoint using `nelmio/api-doc-bundle`, exposed via SwaggerUI for interactive browsing and a raw JSON spec for tooling integration.

Additionally, the `backend-api-engineer` agent file will be updated to enforce keeping OpenAPI annotations in sync whenever endpoints are added or modified, preventing documentation drift.

## Goals

1. **Every public API endpoint is documented** with request/response schemas, HTTP methods, status codes, and descriptions.
2. **SwaggerUI is accessible** at `GET /api/doc` for interactive testing and browsing.
3. **Raw OpenAPI 3.0 JSON** is available at `GET /api/doc.json` for machine consumption (CI validation, client generation, etc.).
4. **Documentation stays current** by embedding the maintenance rule in the backend-api-engineer agent.

## User Stories

### US-1: Developer browses API documentation

> As a **developer**, I want to open SwaggerUI at `/api/doc` so that I can see all available endpoints, their parameters, and response shapes without reading source code.

**Acceptance Criteria:**
- Navigating to `http://localhost:8080/api/doc` renders the SwaggerUI interface.
- All 16 public API endpoints are listed, organized by controller/tag.
- Each endpoint shows: HTTP method, path, summary, request body schema (if applicable), and response schema(s) with status codes.

### US-2: Developer retrieves the raw OpenAPI spec

> As a **developer or CI pipeline**, I want to fetch the OpenAPI 3.0 JSON at `/api/doc.json` so that I can use it for automated validation, client code generation, or import into external tools (Postman, Insomnia, etc.).

**Acceptance Criteria:**
- `GET http://localhost:8080/api/doc.json` returns valid OpenAPI 3.0 JSON with `Content-Type: application/json`.
- The JSON passes validation against the OpenAPI 3.0 schema (e.g., via `swagger-cli validate`).

### US-3: Endpoints are grouped by logical domain

> As a **developer**, I want endpoints grouped into meaningful tags (Game, Oracle, Health, Test) so that the documentation is easy to navigate.

**Acceptance Criteria:**
- SwaggerUI groups endpoints under these tags:
  - **Game** — all `/api/game*` endpoints (12 endpoints)
  - **Oracle** — all `/api/oracle/*` endpoints (2 endpoints)
  - **Health** — `GET /api/health` (1 endpoint)
  - **Test** — `GET /api/test` (1 endpoint)

### US-4: Request and response models are documented

> As a **developer**, I want to see the exact shape of request bodies and response payloads so that I can integrate with the API without trial and error.

**Acceptance Criteria:**
- POST endpoints document their request body JSON schema (required fields, types, constraints).
- All endpoints document at least their success response schema and common error responses (400, 404, 422, 429).
- Enum values (`GamePhase`, `AttributeType`, `RollOutcome`) are reflected in the schema definitions.

### US-5: Agent enforces documentation maintenance

> As a **project maintainer**, I want the backend-api-engineer agent to require OpenAPI annotations on every new or modified endpoint so that documentation never falls behind the implementation.

**Acceptance Criteria:**
- The `backend-api-engineer` agent file (`.claude/agents/backend-api-engineer.md`) contains a rule requiring OpenAPI attributes/annotations on every controller action.
- The rule specifies that adding or modifying an endpoint without updating its OpenAPI documentation is considered incomplete work.

## Technical Approach

### Package Installation

Install `nelmio/api-doc-bundle` via Composer (inside the Docker PHP container):

```bash
docker compose exec backend-php composer require nelmio/api-doc-bundle
```

This will auto-register the bundle via Symfony Flex.

### Configuration

Create/update `config/packages/nelmio_api_doc.yaml`:

```yaml
nelmio_api_doc:
    documentation:
        info:
            title: "La Biblioteca — Oracles API"
            description: "REST API for La Biblioteca, a solo TTRPG journal game."
            version: "1.0.0"
        components:
            securitySchemes: {}
    areas:
        default:
            path_patterns:
                - ^/api/
            host_patterns: []
```

### Routing

Add routes in `config/routes/nelmio_api_doc.yaml`:

```yaml
app.swagger_ui:
    path: /api/doc
    methods: GET
    defaults:
        _controller: nelmio_api_doc.controller.swagger_ui

app.swagger:
    path: /api/doc.json
    methods: GET
    defaults:
        _controller: nelmio_api_doc.controller.swagger
```

### OpenAPI Annotations

Add `OpenApi\Attributes` (PHP 8 attributes) to each controller action. The bundle reads these plus the existing Symfony route metadata to build the spec. Example pattern:

```php
use OpenApi\Attributes as OA;

#[Route('', name: 'api_game_create', methods: ['POST'])]
#[OA\Post(
    summary: 'Create a new game session',
    tags: ['Game'],
)]
#[OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'game_mode', type: 'string', example: 'aventura_rapida'),
        ],
    ),
)]
#[OA\Response(response: 201, description: 'Game created successfully')]
#[OA\Response(response: 422, description: 'Validation failed')]
public function create(Request $request): JsonResponse { ... }
```

This pattern will be applied to all 16 endpoints.

### CORS Consideration

`nelmio/cors-bundle` is already installed and configured. The `/api/doc` and `/api/doc.json` routes fall under the `/api/` prefix and will inherit existing CORS rules. No CORS changes needed.

### Security Consideration

The SwaggerUI and JSON spec routes are under `/api/` which is currently public (no auth). This is acceptable for development. If production lockdown is needed later, it can be handled via Symfony's `access_control` in `security.yaml`.

## Endpoint Inventory (all 16 public endpoints to document)

| # | Method | Path | Controller | Tag |
|---|--------|------|------------|-----|
| 1 | POST | `/api/game` | GameController::create | Game |
| 2 | GET | `/api/games` | GameController::list | Game |
| 3 | GET | `/api/game/{id}` | GameController::get | Game |
| 4 | GET | `/api/game/{id}/export` | GameController::export | Game |
| 5 | POST | `/api/game/{id}/prologue` | GameController::prologue | Game |
| 6 | POST | `/api/game/{id}/chapter/book` | GameController::chapterBook | Game |
| 7 | POST | `/api/game/{id}/chapter/roll` | GameController::chapterRoll | Game |
| 8 | POST | `/api/game/{id}/epilogue/book` | GameController::epilogueBook | Game |
| 9 | POST | `/api/game/{id}/epilogue/action` | GameController::epilogueAction | Game |
| 10 | POST | `/api/game/{id}/epilogue/final` | GameController::epilogueFinal | Game |
| 11 | POST | `/api/game/{id}/journal` | GameController::journalCreate | Game |
| 12 | GET | `/api/game/{id}/journal` | GameController::journalList | Game |
| 13 | GET | `/api/oracle/tables` | OracleController::tables | Oracle |
| 14 | GET | `/api/oracle/random-setting` | OracleController::randomSetting | Oracle |
| 15 | GET | `/api/health` | HealthController::health | Health |
| 16 | GET | `/api/test` | TestController::index | Test |

## Out of Scope

- **Admin panel endpoints** (`/admin/*`) — these are EasyAdmin web routes, not REST API endpoints. They are excluded from the OpenAPI spec.
- **Authentication on SwaggerUI** — the API is currently public; adding auth to the docs page is a separate concern.
- **Automated OpenAPI spec validation in CI** — this can be added as a follow-up.
- **API versioning** — no version prefix changes; the spec documents the current API as-is.
- **Client SDK generation** — the raw spec enables this, but generating clients is not part of this feature.
- **Response schema enforcement at runtime** — the OpenAPI spec is documentation only; it does not validate actual responses.

## Dependencies

| Dependency | Status | Notes |
|------------|--------|-------|
| `nelmio/api-doc-bundle` | **To install** | Symfony 7.2 compatible; provides SwaggerUI controller and OpenAPI spec generation |
| `nelmio/cors-bundle` | **Already installed** | CORS rules already cover `/api/*` prefix; no changes needed |
| PHP OpenAPI Attributes | **Bundled with nelmio/api-doc-bundle** | `zircote/swagger-php` is pulled in as a transitive dependency |
| Symfony Asset component | **Verify installed** | SwaggerUI assets may require `symfony/asset`; Flex should handle this |

## Risks

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| `nelmio/api-doc-bundle` compatibility with Symfony 7.2 | Low | High | Check bundle version constraints before installing; the bundle actively supports Symfony 7.x |
| Documentation drift after initial setup | Medium | Medium | Mitigated by updating the backend-api-engineer agent to enforce annotation maintenance on every endpoint change |
| SwaggerUI assets not loading in Docker | Low | Low | Verify asset installation (`assets:install`) runs in the container build; add to Dockerfile if missing |
| Large response schemas make SwaggerUI slow | Low | Low | Keep schemas focused on top-level properties; avoid deeply nested recursive definitions |

## Implementation Milestones

1. **Install and configure** — Install `nelmio/api-doc-bundle`, create config and routing files. Verify SwaggerUI loads at `/api/doc` (even if empty).
2. **Annotate all 16 endpoints** — Add OpenAPI PHP attributes to every controller action with tags, request bodies, and response schemas.
3. **Define shared schemas** — Create reusable schema definitions for GameState, Book, JournalEntry, RollResult, Attribute, and error responses.
4. **Update agent file** — Add the documentation maintenance rule to `.claude/agents/backend-api-engineer.md`.
5. **Validate** — Confirm the JSON spec at `/api/doc.json` is valid OpenAPI 3.0 and all 16 endpoints appear correctly in SwaggerUI.
