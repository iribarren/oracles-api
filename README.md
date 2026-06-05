# Oracles API

Symfony 7.2 REST API providing oracle tables, game session management, and dice mechanics for tabletop RPG games. Currently powers [The Library](https://github.com/iribarren/thelibrary) but designed to serve multiple games in the future.

## Tech Stack

| Component | Technology | Version |
|-----------|-----------|---------|
| Framework | Symfony | 7.2 |
| Language | PHP | 8.3 |
| Database | MySQL | 8.0 |
| ORM | Doctrine | 3.x |
| Admin | EasyAdmin | 4.x |
| Web server | Nginx + PHP-FPM | Alpine |
| Container | Docker | — |

## Development

Served at `http://localhost:8080` via Docker. Part of the `biblioteca` workspace — run `docker compose up -d` from the parent directory.

### Setup

```bash
# From the parent (biblioteca) directory
docker compose up -d

# Generate JWT keypair (required on every new machine — keys are not committed to git)
docker compose exec backend-php php bin/console lexik:jwt:generate-keypair

# Run migrations
docker compose exec backend-php php bin/console doctrine:migrations:migrate --no-interaction

# Seed database (oracle tables + admin user)
docker compose exec backend-php php bin/console doctrine:fixtures:load --no-interaction
```

> **Note:** The JWT keypair (`config/jwt/private.pem` and `public.pem`) is excluded from git for security. It must be generated once per machine/environment.

### Default Admin Credentials

| Field | Value |
|-------|-------|
| Email | `admin@biblioteca.local` |
| Password | `admin123` |

### Access Points

| Service | URL |
|---------|-----|
| API | `http://localhost:8080/api/*` |
| Admin panel | `http://localhost:8080/admin` |
| Health check | `http://localhost:8080/api/health` |

## Admin Panel

EasyAdmin dashboard at `/admin` with form-login authentication. Manage:

- **Oracle Categories** — genre, epoch, color, binding, smell, interior
- **Oracle Options** — values and hints per category, activation toggle
- **Game Sessions** — read-only view and deletion

## API Reference

Swagger is the single source of truth for endpoint signatures, request/response schemas, and auth requirements.

| Swagger UI | URL | Access |
|-----------|-----|--------|
| Public (Auth + Oracle + Health) | `http://localhost:8080/api/doc` | Public |
| Full (all endpoints) | `http://localhost:8080/admin/api/doc` | `ROLE_ADMIN` session required |
| Raw OpenAPI JSON (public) | `http://localhost:8080/api/doc.json` | Public |
| Raw OpenAPI JSON (full) | `http://localhost:8080/admin/api/doc.json` | `ROLE_ADMIN` session required |

> In production replace `http://localhost:8080` with `https://biblioteca-api.fly.dev`.

## Architecture

```
oracles-api/
├── src/
│   ├── Controller/         # API controllers + EasyAdmin CRUD
│   ├── Entity/             # Doctrine entities (GameSession, Book, User, etc.)
│   ├── Enum/               # GamePhase, AttributeType, RollOutcome
│   ├── Service/            # GameEngine, DiceService
│   ├── Oracle/             # OracleService, BookGenerator
│   ├── Repository/         # Doctrine repositories
│   └── DataFixtures/       # OracleFixtures, AdminUserFixtures
├── config/                 # Symfony configuration
├── migrations/             # Doctrine migrations
├── templates/              # Twig templates (admin login)
├── Dockerfile              # Development image
└── Dockerfile.prod         # Production multi-stage build
```

## Running Tests

```bash
docker compose exec backend-php php bin/phpunit
```

## License

TBD
