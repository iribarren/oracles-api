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
- `GamePhase` — prologue, chapter_1-3, epilogue_action_1-3, epilogue_final, completed
- `AttributeType` — body, mind, social
- `RollOutcome` — hit, weak_hit, miss

## Services
- `GameEngine` — core game logic, phase transitions, dice resolution, score tracking
- `DiceService` — rolling (1d6, 2d10) and outcome calculation
- `OracleService` — reads oracle tables from DB with hardcoded fallback
- `BookGenerator` — assembles random books from oracle categories

## Security
- Admin panel: form-login authentication, `ROLE_ADMIN` required
- API endpoints (`/api/*`): public, no authentication (game is single-player)
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

## Docker
- `Dockerfile` — development (PHP 8.3-FPM + Xdebug)
- `Dockerfile.prod` — multi-stage production build (opcache, no dev dependencies)
- Internal workdir: `/var/www/backend` (container path, not host directory name)
