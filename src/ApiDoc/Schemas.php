<?php

declare(strict_types=1);

namespace App\ApiDoc;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Attribute',
    description: 'A character attribute (Body, Mind, or Social) with its values.',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'type', type: 'string', enum: ['body', 'mind', 'social']),
        new OA\Property(property: 'base_value', type: 'integer'),
        new OA\Property(property: 'background', type: 'integer'),
        new OA\Property(property: 'background_title', type: 'string', nullable: true),
        new OA\Property(property: 'support', type: 'integer'),
        new OA\Property(property: 'support_title', type: 'string', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'Book',
    description: 'A mystery book generated for a chapter or epilogue.',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'phase', type: 'string', enum: ['prologue', 'chapter_1', 'chapter_2', 'chapter_3', 'epilogue_action_1', 'epilogue_action_2', 'epilogue_action_3', 'epilogue_final', 'completed']),
        new OA\Property(property: 'color', type: 'string'),
        new OA\Property(property: 'color_hint', type: 'string'),
        new OA\Property(property: 'binding', type: 'string'),
        new OA\Property(property: 'binding_hint', type: 'string'),
        new OA\Property(property: 'smell', type: 'string'),
        new OA\Property(property: 'smell_hint', type: 'string'),
        new OA\Property(property: 'interior', type: 'string'),
    ]
)]
#[OA\Schema(
    schema: 'JournalEntry',
    description: 'A narrative entry written by the player.',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'phase', type: 'string', enum: ['prologue', 'chapter_1', 'chapter_2', 'chapter_3', 'epilogue_action_1', 'epilogue_action_2', 'epilogue_action_3', 'epilogue_final', 'completed']),
        new OA\Property(property: 'content', type: 'string'),
        new OA\Property(property: 'book_id', type: 'integer', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'RollResult',
    description: 'The result of a dice roll (1d6 + modifier vs 2d10).',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'phase', type: 'string', enum: ['prologue', 'chapter_1', 'chapter_2', 'chapter_3', 'epilogue_action_1', 'epilogue_action_2', 'epilogue_action_3', 'epilogue_final', 'completed']),
        new OA\Property(property: 'action_number', type: 'integer', nullable: true),
        new OA\Property(property: 'action_die', type: 'integer', minimum: 1, maximum: 6),
        new OA\Property(property: 'challenge_die_1', type: 'integer', minimum: 1, maximum: 10),
        new OA\Property(property: 'challenge_die_2', type: 'integer', minimum: 1, maximum: 10),
        new OA\Property(property: 'modifier', type: 'integer'),
        new OA\Property(property: 'action_score', type: 'integer'),
        new OA\Property(property: 'outcome', type: 'string', enum: ['hit', 'weak_hit', 'miss']),
        new OA\Property(property: 'attribute_type', type: 'string', enum: ['body', 'mind', 'social'], nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'GameSession',
    description: 'Full game state including all nested data.',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'character_name', type: 'string', nullable: true),
        new OA\Property(property: 'character_description', type: 'string', nullable: true),
        new OA\Property(property: 'genre', type: 'string', nullable: true),
        new OA\Property(property: 'epoch', type: 'string', nullable: true),
        new OA\Property(property: 'current_phase', type: 'string', enum: ['prologue', 'chapter_1', 'chapter_2', 'chapter_3', 'epilogue_action_1', 'epilogue_action_2', 'epilogue_action_3', 'epilogue_final', 'completed']),
        new OA\Property(property: 'game_mode', type: 'string', example: 'aventura_rapida'),
        new OA\Property(property: 'overcome_score', type: 'integer'),
        new OA\Property(property: 'support_used', type: 'boolean'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'attributes', type: 'array', items: new OA\Items(ref: '#/components/schemas/Attribute')),
        new OA\Property(property: 'books', type: 'array', items: new OA\Items(ref: '#/components/schemas/Book')),
        new OA\Property(property: 'journal_entries', type: 'array', items: new OA\Items(ref: '#/components/schemas/JournalEntry')),
        new OA\Property(property: 'roll_results', type: 'array', items: new OA\Items(ref: '#/components/schemas/RollResult')),
    ]
)]
#[OA\Schema(
    schema: 'GameSummary',
    description: 'Abbreviated game session for list views.',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'character_name', type: 'string', nullable: true),
        new OA\Property(property: 'genre', type: 'string', nullable: true),
        new OA\Property(property: 'epoch', type: 'string', nullable: true),
        new OA\Property(property: 'current_phase', type: 'string', enum: ['prologue', 'chapter_1', 'chapter_2', 'chapter_3', 'epilogue_action_1', 'epilogue_action_2', 'epilogue_action_3', 'epilogue_final', 'completed']),
        new OA\Property(property: 'phase_label', type: 'string', example: 'Capítulo I'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'RollResponse',
    description: 'Response containing a roll result and the updated game state.',
    properties: [
        new OA\Property(property: 'roll_result', ref: '#/components/schemas/RollResult'),
        new OA\Property(property: 'game', ref: '#/components/schemas/GameSession'),
    ]
)]
#[OA\Schema(
    schema: 'GameExport',
    description: 'Print-ready export of a completed game journal.',
    properties: [
        new OA\Property(property: 'title', type: 'string', example: 'La Biblioteca'),
        new OA\Property(property: 'character_name', type: 'string', nullable: true),
        new OA\Property(property: 'character_description', type: 'string', nullable: true),
        new OA\Property(property: 'genre', type: 'string', nullable: true),
        new OA\Property(property: 'epoch', type: 'string', nullable: true),
        new OA\Property(property: 'final_outcome', type: 'string', enum: ['hit', 'weak_hit', 'miss'], nullable: true),
        new OA\Property(property: 'overcome_score', type: 'integer'),
        new OA\Property(
            property: 'entries',
            type: 'array',
            items: new OA\Items(properties: [
                new OA\Property(property: 'phase', type: 'string'),
                new OA\Property(property: 'phase_label', type: 'string'),
                new OA\Property(property: 'content', type: 'string'),
                new OA\Property(
                    property: 'book',
                    nullable: true,
                    properties: [
                        new OA\Property(property: 'color', type: 'string'),
                        new OA\Property(property: 'binding', type: 'string'),
                    ],
                    type: 'object'
                ),
                new OA\Property(
                    property: 'roll',
                    nullable: true,
                    properties: [
                        new OA\Property(property: 'action_die', type: 'integer'),
                        new OA\Property(property: 'challenge_die_1', type: 'integer'),
                        new OA\Property(property: 'challenge_die_2', type: 'integer'),
                        new OA\Property(property: 'action_score', type: 'integer'),
                        new OA\Property(property: 'outcome', type: 'string', enum: ['hit', 'weak_hit', 'miss']),
                        new OA\Property(property: 'attribute_type', type: 'string', nullable: true),
                    ],
                    type: 'object'
                ),
            ])
        ),
        new OA\Property(property: 'attributes', type: 'array', items: new OA\Items(ref: '#/components/schemas/Attribute')),
    ]
)]
#[OA\Schema(
    schema: 'Error',
    properties: [
        new OA\Property(property: 'error', type: 'string'),
    ]
)]
#[OA\Schema(
    schema: 'ValidationError',
    properties: [
        new OA\Property(property: 'error', type: 'string', example: 'Validation failed'),
        new OA\Property(property: 'details', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'string')),
    ]
)]
class Schemas {}
