# Oracles API — Backend

## Project Context
Symfony 7.2 REST API for TTRPG games. Currently powers "The Library" (La Biblioteca). Part of the `biblioteca` workspace. Frontend is in `../thelibrary/`.

## Tech Stack
- PHP 8.3, Symfony 7.2, Doctrine ORM 3.x, MySQL 8.0
- EasyAdmin 4.x for admin panel at `/admin`
- Docker: Nginx (proxy) + PHP-FPM

## Entities
- `GameSession` — UUID PK, character data, game phase, overcome score
- `Attribute` — body/mind/social with base, background, support values
- `Book` — generated per chapter (color, binding, smell, interior + hints)
- `JournalEntry` — player narrative, tied to phase and optionally to a book
- `RollResult` — dice results (1d6 + modifier vs 2d10), outcome enum
- `OracleCategory` — 6 categories: color, binding, smell, interior, genre, epoch
- `OracleOption` — values per category with hints, display order, active flag
- `User` — admin authentication (email, hashed password, roles)

## Enums
- `GamePhase` — prologue, chapter_1-3, epilogue_book, epilogue_action_1-3, epilogue_final, completed
- `AttributeType` — body, mind, social
- `RollOutcome` — hit, weak_hit, miss

## Services
- `GameEngine` — core game logic, phase transitions, dice resolution, score tracking
- `DiceService` — rolling (1d6, 2d10) and outcome calculation
- `OracleService` — reads oracle tables from DB with hardcoded fallback
- `BookGenerator` — assembles random books from oracle categories

## Security
- Admin panel: form-login authentication, `ROLE_ADMIN` required
- API game endpoints (`/api/game/*`): public (no role required); `GameSessionVoter` enforces ownership — ownerless sessions are public, owned sessions are restricted to their owner (403 otherwise)
- API player/auth endpoints: JWT stateless auth via Lexik bundle; `ROLE_PLAYER` for `/api/player`, `IS_AUTHENTICATED_FULLY` for `/api/auth/me`
- CORS configured for frontend origin

## Key Conventions
- All code (variables, functions, comments) MUST be in English
- Doctrine attribute mapping (not annotations, not XML)
- snake_case for DB columns (Doctrine underscore naming strategy), camelCase for PHP properties
- PHP strict types in all files
- Enums for domain values (GamePhase, AttributeType, RollOutcome)

## Database
- Migrations in `migrations/` — run via `doctrine:migrations:migrate`
- Fixtures in `src/DataFixtures/` — seeds oracle data + admin user
- UUID for GameSession PK, auto-increment integers for other entities

## Behaviour specs (Behat) — source of truth

The project uses Spec-Driven Development. The `features/` directory contains executable Gherkin specs that ARE the contract for every business rule. Read them before changing any logic.

**68 scenarios across 9 feature files — all must stay green.**

| Feature file | What it pins |
| --- | --- |
| `features/prologue.feature` | Initial state, character creation, phase transition to chapter_1 |
| `features/chapters.feature` | hit/weak_hit/miss effects on background/support, modifier math, phase transitions, attribute reuse ban, support title |
| `features/epilogue.feature` | epilogue_book discovery phase, overcome_score accumulation, single-use support bonus, decoupled action roll/advance (mirrors chapters), final roll outcomes, full playthrough |
| `features/authentication.feature` | Registration (validation, anti-enumeration), login, throttling, profile, token refresh |
| `features/game-lifecycle.feature` | Anonymous vs owned sessions, GameSessionVoter (403), player session listing |
| `features/oracles.feature` | Fallback to constants (empty DB) and DB-first read when seeded |
| `features/journal.feature` | Entry saving, HTML sanitization, book linking, chronological order, ownership |
| `features/export.feature` | Full document structure, ownership |
| `features/health.feature` | /api/health (DB up), /api/test (connectivity) |

```bash
docker compose exec backend-php vendor/bin/behat          # run all specs
docker compose exec backend-php vendor/bin/behat --dry-run # verify wiring
```

**Mandatory:** run the full suite before committing. A red scenario is a broken contract.

## Unit and integration tests (PHPUnit)

- Every new development must maintain code coverage with meaningful tests
- Before committing a new feature, all tests must pass: `docker compose exec backend-php vendor/bin/phpunit`

## Docker
- `Dockerfile` — development (PHP 8.3-FPM + Xdebug)
- `Dockerfile.prod` — multi-stage production build (opcache, no dev dependencies)
- Internal workdir: `/var/www/backend` (container path, not host directory name)