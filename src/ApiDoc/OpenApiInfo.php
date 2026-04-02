<?php

declare(strict_types=1);

namespace App\ApiDoc;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: 'REST API for La Biblioteca, a solo TTRPG journaling game. Manages game sessions, dice rolls, books, and journal entries.',
    title: 'La Biblioteca — Oracles API',
)]
#[OA\Tag(name: 'Game', description: 'Game session lifecycle: creation, phase progression, dice rolls, and journal entries.')]
#[OA\Tag(name: 'Oracle', description: 'Oracle lookup tables and random setting generation.')]
#[OA\Tag(name: 'System', description: 'Health check and connectivity endpoints.')]
class OpenApiInfo {}
