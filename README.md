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

## API Endpoints

### Authentication

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `POST` | `/api/auth/register` | Public | Create a player account |
| `POST` | `/api/auth/login` | Public | Log in and receive JWT + refresh token |
| `POST` | `/api/auth/refresh` | Public (refresh token) | Obtain a new access token |
| `GET` | `/api/auth/me` | Bearer | Return current user profile |

### Player

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/api/player/sessions` | Bearer (`ROLE_PLAYER`) | List authenticated player's game sessions |

### Game Flow

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `POST` | `/api/game` | Optional Bearer | Create a new game session (linked to user if authenticated) |
| `GET` | `/api/game/{id}` | Optional Bearer | Get full game state |
| `GET` | `/api/games` | Public | List all game sessions |
| `POST` | `/api/game/{id}/prologue` | Optional Bearer | Complete the prologue phase |
| `POST` | `/api/game/{id}/chapter/book` | Optional Bearer | Generate a chapter book |
| `POST` | `/api/game/{id}/chapter/roll` | Optional Bearer | Roll dice for the current chapter |
| `POST` | `/api/game/{id}/epilogue/book` | Optional Bearer | Generate an epilogue book |
| `POST` | `/api/game/{id}/epilogue/action` | Optional Bearer | Roll an epilogue action |
| `POST` | `/api/game/{id}/epilogue/final` | Optional Bearer | Perform the final roll |

> **Ownership:** Sessions created by an authenticated player are private — only that player can access them. Sessions created without authentication have no owner and are publicly accessible.

### Journal

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `POST` | `/api/game/{id}/journal` | Optional Bearer | Save a journal entry |
| `GET` | `/api/game/{id}/journal` | Optional Bearer | List journal entries |
| `GET` | `/api/game/{id}/export` | Optional Bearer | Export full journal (print-ready) |

### Oracle

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/api/oracle/tables` | Public | Get all oracle tables |
| `GET` | `/api/oracle/random-setting` | Public | Random genre + epoch pair |

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
