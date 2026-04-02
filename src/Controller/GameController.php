<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Attribute;
use App\Entity\Book;
use App\Entity\GameSession;
use App\Entity\JournalEntry;
use App\Entity\RollResult;
use App\Enum\AttributeType;
use App\Enum\GamePhase;
use App\Repository\BookRepository;
use App\Repository\GameSessionRepository;
use App\Service\GameEngine;
use InvalidArgumentException;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/game')]
class GameController extends AbstractController
{
    /** Maps GamePhase values to their Spanish display labels. */
    private const array PHASE_LABELS = [
        'prologue'           => 'Prólogo',
        'chapter_1'          => 'Capítulo I',
        'chapter_2'          => 'Capítulo II',
        'chapter_3'          => 'Capítulo III',
        'epilogue_action_1'  => 'Epílogo — Acción 1',
        'epilogue_action_2'  => 'Epílogo — Acción 2',
        'epilogue_action_3'  => 'Epílogo — Acción 3',
        'epilogue_final'     => 'Epílogo — Tirada Final',
        'completed'          => 'Completado',
    ];

    public function __construct(
        private readonly GameEngine             $gameEngine,
        private readonly GameSessionRepository  $gameSessionRepository,
        private readonly BookRepository         $bookRepository,
        private readonly RateLimiterFactory     $gameRollLimiter,
    ) {}

    // -------------------------------------------------------------------------
    // POST /api/game — Create new game
    // -------------------------------------------------------------------------

    /** Allowed game mode identifiers. Extend this list as new modes are added. */
    private const array ALLOWED_GAME_MODES = ['aventura_rapida'];

    #[Route('', name: 'api_game_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data     = $this->decodeJson($request) ?? [];
        $gameMode = \trim((string) ($data['game_mode'] ?? 'aventura_rapida'));

        if (!\in_array($gameMode, self::ALLOWED_GAME_MODES, true)) {
            return $this->json(
                ['error' => 'Validation failed', 'details' => ['game_mode' => 'Unknown game mode.']],
                422,
            );
        }

        try {
            $game = $this->gameEngine->createGame($gameMode);
        } catch (LogicException | InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json($this->serializeGameState($game), 201);
    }

    // -------------------------------------------------------------------------
    // GET /api/games — List all game sessions (most recent first)
    // -------------------------------------------------------------------------

    #[Route('s', name: 'api_games_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $games = $this->gameSessionRepository->findAllOrderedByDate();

        return $this->json(\array_map(function (GameSession $game): array {
            $phase = $game->getCurrentPhase();

            return [
                'id'            => (string) $game->getId(),
                'character_name' => $game->getCharacterName(),
                'genre'          => $game->getGenre(),
                'epoch'          => $game->getEpoch(),
                'current_phase'  => $phase->value,
                'phase_label'    => self::PHASE_LABELS[$phase->value] ?? $phase->value,
                'created_at'     => $game->getCreatedAt()->format('c'),
                'updated_at'     => $game->getUpdatedAt()->format('c'),
            ];
        }, $games));
    }

    // -------------------------------------------------------------------------
    // GET /api/game/{id}/export — Export full journal document
    // -------------------------------------------------------------------------

    #[Route('/{id}/export', name: 'api_game_export', methods: ['GET'])]
    public function export(string $id): JsonResponse
    {
        $game = $this->findGame($id);
        if ($game === null) {
            return $this->json(['error' => 'Game session not found'], 404);
        }

        // Build a phase -> RollResult index for O(1) lookup per entry
        /** @var array<string, RollResult> $rollByPhase */
        $rollByPhase = [];
        foreach ($game->getRollResults() as $rollResult) {
            $rollByPhase[$rollResult->getPhase()->value] = $rollResult;
        }

        // Sort journal entries chronologically
        $entries = $game->getJournalEntries()->toArray();
        \usort($entries, static fn(JournalEntry $a, JournalEntry $b) =>
            $a->getCreatedAt() <=> $b->getCreatedAt()
        );

        $serializedEntries = \array_map(function (JournalEntry $entry) use ($rollByPhase): array {
            $phaseValue = $entry->getPhase()->value;
            $book       = $entry->getBook();
            $roll       = $rollByPhase[$phaseValue] ?? null;

            return [
                'phase'       => $phaseValue,
                'phase_label' => self::PHASE_LABELS[$phaseValue] ?? $phaseValue,
                'content'     => $entry->getContent(),
                'book'        => $book !== null ? [
                    'color'   => $book->getColor(),
                    'binding' => $book->getBinding(),
                ] : null,
                'roll' => $roll !== null ? [
                    'action_die'      => $roll->getActionDie(),
                    'challenge_die_1' => $roll->getChallengeDie1(),
                    'challenge_die_2' => $roll->getChallengeDie2(),
                    'action_score'    => $roll->getActionScore(),
                    'outcome'         => $roll->getOutcome()->value,
                    'attribute_type'  => $roll->getAttributeType()?->value,
                ] : null,
            ];
        }, $entries);

        // Derive final outcome from the epilogue_final roll result, if present
        $finalRoll    = $rollByPhase[GamePhase::EPILOGUE_FINAL->value] ?? null;
        $finalOutcome = $finalRoll?->getOutcome()->value;

        $serializedAttributes = \array_map(
            fn(Attribute $a) => [
                'type'             => $a->getType()->value,
                'base_value'       => $a->getBaseValue(),
                'background'       => $a->getBackground(),
                'background_title' => $a->getBackgroundTitle(),
                'support'          => $a->getSupport(),
                'support_title'    => $a->getSupportTitle(),
            ],
            $game->getAttributes()->toArray()
        );

        return $this->json([
            'title'                 => 'La Biblioteca',
            'character_name'        => $game->getCharacterName(),
            'character_description' => $game->getCharacterDescription(),
            'genre'                 => $game->getGenre(),
            'epoch'                 => $game->getEpoch(),
            'final_outcome'         => $finalOutcome,
            'overcome_score'        => $game->getOvercomeScore(),
            'entries'               => $serializedEntries,
            'attributes'            => $serializedAttributes,
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/game/{id} — Get full game state
    // -------------------------------------------------------------------------

    #[Route('/{id}', name: 'api_game_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $game = $this->findGame($id);
        if ($game === null) {
            return $this->json(['error' => 'Game session not found'], 404);
        }

        return $this->json($this->serializeGameState($game));
    }

    // -------------------------------------------------------------------------
    // POST /api/game/{id}/prologue — Complete prologue
    // -------------------------------------------------------------------------

    #[Route('/{id}/prologue', name: 'api_game_prologue', methods: ['POST'])]
    public function prologue(string $id, Request $request): JsonResponse
    {
        $game = $this->findGame($id);
        if ($game === null) {
            return $this->json(['error' => 'Game session not found'], 404);
        }

        $data = $this->decodeJson($request);
        if ($data === null) {
            return $this->json(['error' => 'Invalid request body'], 400);
        }

        $errors = [];

        $characterName        = \trim((string) ($data['character_name'] ?? ''));
        $characterDescription = \trim((string) ($data['character_description'] ?? ''));
        $genre                = \trim((string) ($data['genre'] ?? ''));
        $epoch                = \trim((string) ($data['epoch'] ?? ''));

        if ($characterName === '') {
            $errors['character_name'] = 'This field is required.';
        } elseif (\strlen($characterName) > 255) {
            $errors['character_name'] = 'This field must not exceed 255 characters.';
        }

        if ($characterDescription === '') {
            $errors['character_description'] = 'This field is required.';
        }

        if ($genre === '') {
            $errors['genre'] = 'This field is required.';
        }

        if ($epoch === '') {
            $errors['epoch'] = 'This field is required.';
        }

        if ($errors !== []) {
            return $this->json(['error' => 'Validation failed', 'details' => $errors], 422);
        }

        // Sanitize free-text inputs to prevent XSS
        $characterDescription = \strip_tags($characterDescription);

        try {
            $this->gameEngine->completePrologue($game, $characterName, $characterDescription, $genre, $epoch);
        } catch (LogicException | InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json($this->serializeGameState($game));
    }

    // -------------------------------------------------------------------------
    // POST /api/game/{id}/chapter/book — Generate chapter book
    // -------------------------------------------------------------------------

    #[Route('/{id}/chapter/book', name: 'api_game_chapter_book', methods: ['POST'])]
    public function chapterBook(string $id): JsonResponse
    {
        $game = $this->findGame($id);
        if ($game === null) {
            return $this->json(['error' => 'Game session not found'], 404);
        }

        try {
            $book = $this->gameEngine->generateChapterBook($game);
        } catch (LogicException | InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json($this->serializeBook($book));
    }

    // -------------------------------------------------------------------------
    // POST /api/game/{id}/chapter/roll — Resolve chapter roll
    // -------------------------------------------------------------------------

    #[Route('/{id}/chapter/roll', name: 'api_game_chapter_roll', methods: ['POST'])]
    public function chapterRoll(string $id, Request $request): JsonResponse
    {
        $rateLimitResponse = $this->checkRateLimit($request);
        if ($rateLimitResponse !== null) {
            return $rateLimitResponse;
        }

        $game = $this->findGame($id);
        if ($game === null) {
            return $this->json(['error' => 'Game session not found'], 404);
        }

        $data = $this->decodeJson($request);
        if ($data === null) {
            return $this->json(['error' => 'Invalid request body'], 400);
        }

        $attributeValue = \trim((string) ($data['attribute'] ?? ''));
        $attributeType  = AttributeType::tryFrom($attributeValue);

        if ($attributeType === null) {
            return $this->json(
                ['error' => 'Validation failed', 'details' => ['attribute' => 'Must be one of: body, mind, social.']],
                422
            );
        }

        try {
            $rollResult = $this->gameEngine->resolveChapter($game, $attributeType);
        } catch (LogicException | InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json([
            'roll_result' => $this->serializeRollResult($rollResult),
            'game'        => $this->serializeGameState($game),
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/game/{id}/epilogue/book — Generate epilogue book
    // -------------------------------------------------------------------------

    #[Route('/{id}/epilogue/book', name: 'api_game_epilogue_book', methods: ['POST'])]
    public function epilogueBook(string $id): JsonResponse
    {
        $game = $this->findGame($id);
        if ($game === null) {
            return $this->json(['error' => 'Game session not found'], 404);
        }

        try {
            $book = $this->gameEngine->generateEpilogueBook($game);
        } catch (LogicException | InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json($this->serializeBook($book));
    }

    // -------------------------------------------------------------------------
    // POST /api/game/{id}/epilogue/action — Resolve epilogue action
    // -------------------------------------------------------------------------

    #[Route('/{id}/epilogue/action', name: 'api_game_epilogue_action', methods: ['POST'])]
    public function epilogueAction(string $id, Request $request): JsonResponse
    {
        $rateLimitResponse = $this->checkRateLimit($request);
        if ($rateLimitResponse !== null) {
            return $rateLimitResponse;
        }

        $game = $this->findGame($id);
        if ($game === null) {
            return $this->json(['error' => 'Game session not found'], 404);
        }

        $data = $this->decodeJson($request);
        if ($data === null) {
            return $this->json(['error' => 'Invalid request body'], 400);
        }

        $attributeValue = \trim((string) ($data['attribute'] ?? ''));
        $attributeType  = AttributeType::tryFrom($attributeValue);

        if ($attributeType === null) {
            return $this->json(
                ['error' => 'Validation failed', 'details' => ['attribute' => 'Must be one of: body, mind, social.']],
                422
            );
        }

        $supportAttribute = null;
        if (isset($data['support_attribute']) && $data['support_attribute'] !== null) {
            $supportAttribute = AttributeType::tryFrom(\trim((string) $data['support_attribute']));
            if ($supportAttribute === null) {
                return $this->json(
                    ['error' => 'Validation failed', 'details' => ['support_attribute' => 'Must be one of: body, mind, social.']],
                    422
                );
            }
        }

        try {
            $rollResult = $this->gameEngine->resolveEpilogueAction($game, $attributeType, $supportAttribute);
        } catch (LogicException | InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json([
            'roll_result' => $this->serializeRollResult($rollResult),
            'game'        => $this->serializeGameState($game),
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/game/{id}/epilogue/final — Resolve final roll
    // -------------------------------------------------------------------------

    #[Route('/{id}/epilogue/final', name: 'api_game_epilogue_final', methods: ['POST'])]
    public function epilogueFinal(string $id, Request $request): JsonResponse
    {
        $rateLimitResponse = $this->checkRateLimit($request);
        if ($rateLimitResponse !== null) {
            return $rateLimitResponse;
        }

        $game = $this->findGame($id);
        if ($game === null) {
            return $this->json(['error' => 'Game session not found'], 404);
        }

        try {
            $rollResult = $this->gameEngine->resolveFinalRoll($game);
        } catch (LogicException | InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json([
            'roll_result' => $this->serializeRollResult($rollResult),
            'game'        => $this->serializeGameState($game),
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/game/{id}/journal — Save journal entry
    // -------------------------------------------------------------------------

    #[Route('/{id}/journal', name: 'api_game_journal_create', methods: ['POST'])]
    public function journalCreate(string $id, Request $request): JsonResponse
    {
        $game = $this->findGame($id);
        if ($game === null) {
            return $this->json(['error' => 'Game session not found'], 404);
        }

        $data = $this->decodeJson($request);
        if ($data === null) {
            return $this->json(['error' => 'Invalid request body'], 400);
        }

        $content = \trim((string) ($data['content'] ?? ''));
        if ($content === '') {
            return $this->json(
                ['error' => 'Validation failed', 'details' => ['content' => 'This field is required.']],
                422
            );
        }

        // Sanitize content to prevent XSS
        $content = \strip_tags($content);

        $book = null;
        if (isset($data['book_id']) && $data['book_id'] !== null) {
            $bookId = (int) $data['book_id'];
            $book   = $this->bookRepository->find($bookId);

            if ($book === null) {
                return $this->json(['error' => 'Book not found'], 404);
            }

            // Ensure the book belongs to this game session
            if ($book->getGameSession()->getId()->toRfc4122() !== $game->getId()->toRfc4122()) {
                return $this->json(['error' => 'Book does not belong to this game session'], 403);
            }
        }

        try {
            $entry = $this->gameEngine->saveJournalEntry($game, $content, $book);
        } catch (LogicException | InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json($this->serializeJournalEntry($entry), 201);
    }

    // -------------------------------------------------------------------------
    // GET /api/game/{id}/journal — Get all journal entries
    // -------------------------------------------------------------------------

    #[Route('/{id}/journal', name: 'api_game_journal_list', methods: ['GET'])]
    public function journalList(string $id): JsonResponse
    {
        $game = $this->findGame($id);
        if ($game === null) {
            return $this->json(['error' => 'Game session not found'], 404);
        }

        $entries = $game->getJournalEntries()->toArray();

        \usort($entries, static fn(JournalEntry $a, JournalEntry $b) =>
            $a->getCreatedAt() <=> $b->getCreatedAt()
        );

        return $this->json(\array_map(fn(JournalEntry $j) => $this->serializeJournalEntry($j), $entries));
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function findGame(string $id): ?GameSession
    {
        // UUIDs stored as binary; find() will accept the string representation
        return $this->gameSessionRepository->find($id);
    }

    /**
     * Decodes the JSON request body and returns the data array, or null on failure.
     *
     * @return array<string, mixed>|null
     */
    private function decodeJson(Request $request): ?array
    {
        $content = $request->getContent();
        if ($content === '') {
            return [];
        }

        try {
            $data = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return \is_array($data) ? $data : null;
    }

    /**
     * Checks the sliding-window rate limit keyed by client IP.
     * Returns a 429 response if the limit is exceeded, null otherwise.
     */
    private function checkRateLimit(Request $request): ?JsonResponse
    {
        $limiter = $this->gameRollLimiter->create($request->getClientIp() ?? 'anonymous');
        $limit   = $limiter->consume();

        if (!$limit->isAccepted()) {
            return $this->json(
                ['error' => 'Too many requests. Please slow down.'],
                429,
                ['X-RateLimit-Retry-After' => $limit->getRetryAfter()?->getTimestamp()]
            );
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Serialization helpers
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function serializeGameState(GameSession $game): array
    {
        return [
            'id'                    => (string) $game->getId(),
            'character_name'        => $game->getCharacterName(),
            'character_description' => $game->getCharacterDescription(),
            'genre'                 => $game->getGenre(),
            'epoch'                 => $game->getEpoch(),
            'current_phase'         => $game->getCurrentPhase()->value,
            'game_mode'             => $game->getGameMode(),
            'overcome_score'        => $game->getOvercomeScore(),
            'support_used'          => $game->isSupportUsed(),
            'created_at'            => $game->getCreatedAt()->format('c'),
            'updated_at'            => $game->getUpdatedAt()->format('c'),
            'attributes'            => \array_map(
                fn(Attribute $a) => $this->serializeAttribute($a),
                $game->getAttributes()->toArray()
            ),
            'books'          => \array_map(
                fn(Book $b) => $this->serializeBook($b),
                $game->getBooks()->toArray()
            ),
            'journal_entries' => \array_map(
                fn(JournalEntry $j) => $this->serializeJournalEntry($j),
                $game->getJournalEntries()->toArray()
            ),
            'roll_results'   => \array_map(
                fn(RollResult $r) => $this->serializeRollResult($r),
                $game->getRollResults()->toArray()
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeAttribute(Attribute $attribute): array
    {
        return [
            'id'               => $attribute->getId(),
            'type'             => $attribute->getType()->value,
            'base_value'       => $attribute->getBaseValue(),
            'background'       => $attribute->getBackground(),
            'background_title' => $attribute->getBackgroundTitle(),
            'support'          => $attribute->getSupport(),
            'support_title'    => $attribute->getSupportTitle(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeBook(Book $book): array
    {
        return [
            'id'           => $book->getId(),
            'phase'        => $book->getPhase()->value,
            'color'        => $book->getColor(),
            'color_hint'   => $book->getColorHint(),
            'binding'      => $book->getBinding(),
            'binding_hint' => $book->getBindingHint(),
            'smell'        => $book->getSmell(),
            'smell_hint'   => $book->getSmellHint(),
            'interior'     => $book->getInterior(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeJournalEntry(JournalEntry $entry): array
    {
        return [
            'id'         => $entry->getId(),
            'phase'      => $entry->getPhase()->value,
            'content'    => $entry->getContent(),
            'book_id'    => $entry->getBook()?->getId(),
            'created_at' => $entry->getCreatedAt()->format('c'),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeRollResult(RollResult $result): array
    {
        return [
            'id'              => $result->getId(),
            'phase'           => $result->getPhase()->value,
            'action_number'   => $result->getActionNumber(),
            'action_die'      => $result->getActionDie(),
            'challenge_die_1' => $result->getChallengeDie1(),
            'challenge_die_2' => $result->getChallengeDie2(),
            'modifier'        => $result->getModifier(),
            'action_score'    => $result->getActionScore(),
            'outcome'         => $result->getOutcome()->value,
            'attribute_type'  => $result->getAttributeType()?->value,
        ];
    }
}
