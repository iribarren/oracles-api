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

# Run migrations
docker compose exec backend-php php bin/console doctrine:migrations:migrate --no-interaction

# Seed database (oracle tables + admin user)
docker compose exec backend-php php bin/console doctrine:fixtures:load --no-interaction
```

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

### Game Flow

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/game` | Create a new game session |
| `GET` | `/api/game/{id}` | Get full game state |
| `GET` | `/api/games` | List all game sessions |
| `POST` | `/api/game/{id}/prologue` | Complete the prologue phase |
| `POST` | `/api/game/{id}/chapter/book` | Generate a chapter book |
| `POST` | `/api/game/{id}/chapter/roll` | Roll dice for the current chapter |
| `POST` | `/api/game/{id}/epilogue/book` | Generate an epilogue book |
| `POST` | `/api/game/{id}/epilogue/action` | Roll an epilogue action |
| `POST` | `/api/game/{id}/epilogue/final` | Perform the final roll |

### Journal

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/game/{id}/journal` | Save a journal entry |
| `GET` | `/api/game/{id}/journal` | List journal entries |
| `GET` | `/api/game/{id}/export` | Export full journal (print-ready) |

### Oracle

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/oracle/tables` | Get all oracle tables |
| `GET` | `/api/oracle/random-setting` | Random genre + epoch pair |

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
