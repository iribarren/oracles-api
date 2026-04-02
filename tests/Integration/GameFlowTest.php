<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integration tests covering the full game API flow.
 *
 * Each test method creates its own game session and cleans up after itself
 * using the shared tearDown that purges all game_sessions rows (which cascades
 * to attributes, books, journal_entries and roll_results).
 */
class GameFlowTest extends WebTestCase
{
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $browser;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        // createClient() boots the kernel; call it before getContainer()
        $this->browser = static::createClient();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Clean slate before each test
        $this->purgeDatabase();
    }

    protected function tearDown(): void
    {
        $this->purgeDatabase();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function purgeDatabase(): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $conn->executeStatement('DELETE FROM journal_entries');
        $conn->executeStatement('DELETE FROM roll_results');
        $conn->executeStatement('DELETE FROM books');
        $conn->executeStatement('DELETE FROM attributes');
        $conn->executeStatement('DELETE FROM game_sessions');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * @return array<string, mixed>
     */
    private function postJson(string $url, array $body = []): array
    {
        $this->browser->request(
            'POST',
            $url,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR)
        );

        $response = $this->browser->getResponse();
        return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

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

    /**
     * Creates a game via the API and returns the response body.
     *
     * @return array<string, mixed>
     */
    private function createGame(): array
    {
        $data = $this->postJson('/api/game');
        $this->assertSame(201, $this->getLastStatusCode());
        return $data;
    }

    /**
     * Completes the prologue with default fixture data.
     *
     * @return array<string, mixed>
     */
    private function completePrologue(string $gameId, array $overrides = []): array
    {
        $payload = array_merge([
            'character_name'        => 'Librarian',
            'character_description' => 'A keeper of ancient tomes.',
            'genre'                 => 'Fantasía',
            'epoch'                 => 'Medieval',
        ], $overrides);

        return $this->postJson("/api/game/{$gameId}/prologue", $payload);
    }

    /**
     * Runs a chapter: generates book, rolls, and advances. Returns the advance response.
     *
     * @return array<string, mixed>
     */
    private function runChapter(string $gameId, string $attribute): array
    {
        // Generate chapter book (ignore response)
        $this->postJson("/api/game/{$gameId}/chapter/book");
        $this->assertSame(200, $this->getLastStatusCode());

        // Roll for the chapter
        $this->postJson("/api/game/{$gameId}/chapter/roll", ['attribute' => $attribute]);
        $this->assertSame(200, $this->getLastStatusCode());

        // Advance to the next phase
        $advanceResponse = $this->postJson("/api/game/{$gameId}/chapter/advance");
        $this->assertSame(200, $this->getLastStatusCode());
        return ['game' => $advanceResponse];
    }

    /**
     * Runs a full epilogue: book + 3 actions + final roll.
     *
     * @return array<string, mixed> The final roll response
     */
    private function runEpilogue(string $gameId): array
    {
        $this->postJson("/api/game/{$gameId}/epilogue/book");
        $this->assertSame(200, $this->getLastStatusCode());

        $attributes = ['body', 'mind', 'social'];
        foreach ($attributes as $attribute) {
            $this->postJson("/api/game/{$gameId}/epilogue/action", ['attribute' => $attribute]);
            $this->assertSame(200, $this->getLastStatusCode());
        }

        $finalResponse = $this->postJson("/api/game/{$gameId}/epilogue/final");
        $this->assertSame(200, $this->getLastStatusCode());
        return $finalResponse;
    }

    // -------------------------------------------------------------------------
    // POST /api/game
    // -------------------------------------------------------------------------

    public function testCreateGameReturns201(): void
    {
        $this->postJson('/api/game');
        $this->assertSame(201, $this->getLastStatusCode());
    }

    public function testCreateGameResponseHasId(): void
    {
        $data = $this->createGame();
        $this->assertArrayHasKey('id', $data);
        $this->assertNotEmpty($data['id']);
    }

    public function testCreateGameStartsInProloguePhase(): void
    {
        $data = $this->createGame();
        $this->assertSame('prologue', $data['current_phase']);
    }

    public function testCreateGameHasThreeAttributes(): void
    {
        $data = $this->createGame();
        $this->assertCount(3, $data['attributes']);
    }

    public function testCreateGameAttributeTypesAreBodyMindSocial(): void
    {
        $data  = $this->createGame();
        $types = array_column($data['attributes'], 'type');
        sort($types);
        $this->assertSame(['body', 'mind', 'social'], $types);
    }

    public function testCreateGameAttributeBaseValueIsOne(): void
    {
        $data = $this->createGame();
        foreach ($data['attributes'] as $attribute) {
            $this->assertSame(1, $attribute['base_value']);
        }
    }

    public function testCreateGameOvercomeScoreIsZero(): void
    {
        $data = $this->createGame();
        $this->assertSame(0, $data['overcome_score']);
    }

    public function testCreateGameSupportUsedIsFalse(): void
    {
        $data = $this->createGame();
        $this->assertFalse($data['support_used']);
    }

    // -------------------------------------------------------------------------
    // GET /api/game/{id}
    // -------------------------------------------------------------------------

    public function testGetGameReturns200(): void
    {
        $game = $this->createGame();
        $this->getJson("/api/game/{$game['id']}");
        $this->assertSame(200, $this->getLastStatusCode());
    }

    public function testGetGameReturnsCorrectId(): void
    {
        $game     = $this->createGame();
        $fetched  = $this->getJson("/api/game/{$game['id']}");
        $this->assertSame($game['id'], $fetched['id']);
    }

    public function testGetGameNotFoundReturns404(): void
    {
        $this->getJson('/api/game/00000000-0000-0000-0000-000000000000');
        $this->assertSame(404, $this->getLastStatusCode());
    }

    public function testGetGameNotFoundResponseHasErrorKey(): void
    {
        $data = $this->getJson('/api/game/00000000-0000-0000-0000-000000000000');
        $this->assertArrayHasKey('error', $data);
    }

    // -------------------------------------------------------------------------
    // POST /api/game/{id}/prologue
    // -------------------------------------------------------------------------

    public function testCompletePrologueReturns200(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        $this->assertSame(200, $this->getLastStatusCode());
    }

    public function testCompletePrologueAdvancesToChapter1(): void
    {
        $game     = $this->createGame();
        $response = $this->completePrologue($game['id']);
        $this->assertSame('chapter_1', $response['current_phase']);
    }

    public function testCompletePrologueSetsCharacterName(): void
    {
        $game     = $this->createGame();
        $response = $this->completePrologue($game['id'], ['character_name' => 'Aldric']);
        $this->assertSame('Aldric', $response['character_name']);
    }

    public function testCompletePrologueSetsGenre(): void
    {
        $game     = $this->createGame();
        $response = $this->completePrologue($game['id'], ['genre' => 'Romance']);
        $this->assertSame('Romance', $response['genre']);
    }

    public function testCompletePrologueSetsEpoch(): void
    {
        $game     = $this->createGame();
        $response = $this->completePrologue($game['id'], ['epoch' => 'Victoriana']);
        $this->assertSame('Victoriana', $response['epoch']);
    }

    public function testCompletePrologueMissingCharacterNameReturns422(): void
    {
        $game = $this->createGame();
        $this->postJson("/api/game/{$game['id']}/prologue", [
            'character_description' => 'A keeper.',
            'genre'                 => 'Fantasía',
            'epoch'                 => 'Medieval',
        ]);
        $this->assertSame(422, $this->getLastStatusCode());
    }

    public function testCompletePrologueMissingGenreReturns422(): void
    {
        $game = $this->createGame();
        $this->postJson("/api/game/{$game['id']}/prologue", [
            'character_name'        => 'Librarian',
            'character_description' => 'A keeper.',
            'epoch'                 => 'Medieval',
        ]);
        $this->assertSame(422, $this->getLastStatusCode());
    }

    public function testCompletePrologueMissingEpochReturns422(): void
    {
        $game = $this->createGame();
        $this->postJson("/api/game/{$game['id']}/prologue", [
            'character_name'        => 'Librarian',
            'character_description' => 'A keeper.',
            'genre'                 => 'Fantasía',
        ]);
        $this->assertSame(422, $this->getLastStatusCode());
    }

    public function testCompletePrologueMissingDescriptionReturns422(): void
    {
        $game = $this->createGame();
        $this->postJson("/api/game/{$game['id']}/prologue", [
            'character_name' => 'Librarian',
            'genre'          => 'Fantasía',
            'epoch'          => 'Medieval',
        ]);
        $this->assertSame(422, $this->getLastStatusCode());
    }

    public function testCompletePrologueValidationResponseHasDetailsKey(): void
    {
        $game = $this->createGame();
        $data = $this->postJson("/api/game/{$game['id']}/prologue", []);
        $this->assertArrayHasKey('details', $data);
    }

    public function testCompletePrologueSanitizesHtmlTagsFromDescription(): void
    {
        $game     = $this->createGame();
        $response = $this->completePrologue($game['id'], [
            'character_description' => '<script>alert("xss")</script>A keeper.',
        ]);
        $this->assertStringNotContainsString('<script>', $response['character_description']);
        $this->assertStringContainsString('A keeper.', $response['character_description']);
    }

    // -------------------------------------------------------------------------
    // POST /api/game/{id}/chapter/book
    // -------------------------------------------------------------------------

    public function testGenerateChapterBookReturns200(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        $this->postJson("/api/game/{$game['id']}/chapter/book");
        $this->assertSame(200, $this->getLastStatusCode());
    }

    public function testGenerateChapterBookResponseHasBookFields(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        $bookData = $this->postJson("/api/game/{$game['id']}/chapter/book");

        $this->assertArrayHasKey('id',           $bookData);
        $this->assertArrayHasKey('phase',        $bookData);
        $this->assertArrayHasKey('color',        $bookData);
        $this->assertArrayHasKey('binding',      $bookData);
        $this->assertArrayHasKey('smell',        $bookData);
        $this->assertArrayHasKey('interior',     $bookData);
    }

    public function testGenerateChapterBookInNonChapterPhaseReturns400(): void
    {
        $game = $this->createGame();
        // Still in prologue — chapter/book should fail
        $this->postJson("/api/game/{$game['id']}/chapter/book");
        $this->assertSame(400, $this->getLastStatusCode());
    }

    // -------------------------------------------------------------------------
    // POST /api/game/{id}/chapter/roll
    // -------------------------------------------------------------------------

    public function testChapterRollReturns200(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        $this->postJson("/api/game/{$game['id']}/chapter/book");
        $this->postJson("/api/game/{$game['id']}/chapter/roll", ['attribute' => 'body']);
        $this->assertSame(200, $this->getLastStatusCode());
    }

    public function testChapterRollResponseHasRollResultAndGame(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        $this->postJson("/api/game/{$game['id']}/chapter/book");
        $data = $this->postJson("/api/game/{$game['id']}/chapter/roll", ['attribute' => 'body']);

        $this->assertArrayHasKey('roll_result', $data);
        $this->assertArrayHasKey('game',        $data);
    }

    public function testChapterRollDoesNotAdvancePhase(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        $this->postJson("/api/game/{$game['id']}/chapter/book");
        $data = $this->postJson("/api/game/{$game['id']}/chapter/roll", ['attribute' => 'body']);

        $this->assertSame('chapter_1', $data['game']['current_phase']);
    }

    public function testChapterRollWithInvalidAttributeReturns422(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        $this->postJson("/api/game/{$game['id']}/chapter/book");
        $this->postJson("/api/game/{$game['id']}/chapter/roll", ['attribute' => 'invalid_attr']);
        $this->assertSame(422, $this->getLastStatusCode());
    }

    public function testChapterRollReusingAttributeReturns400(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);

        // Chapter 1 with 'body'
        $this->runChapter($game['id'], 'body');

        // Chapter 2 — try to reuse 'body'
        $this->postJson("/api/game/{$game['id']}/chapter/book");
        $this->postJson("/api/game/{$game['id']}/chapter/roll", ['attribute' => 'body']);
        $this->assertSame(400, $this->getLastStatusCode());
    }

    // -------------------------------------------------------------------------
    // POST /api/game/{id}/chapter/advance
    // -------------------------------------------------------------------------

    public function testChapterAdvanceReturns200(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        $this->postJson("/api/game/{$game['id']}/chapter/book");
        $this->postJson("/api/game/{$game['id']}/chapter/roll", ['attribute' => 'body']);

        $this->postJson("/api/game/{$game['id']}/chapter/advance");
        $this->assertSame(200, $this->getLastStatusCode());
    }

    public function testChapterAdvanceMovesToNextPhase(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        $this->postJson("/api/game/{$game['id']}/chapter/book");
        $this->postJson("/api/game/{$game['id']}/chapter/roll", ['attribute' => 'body']);

        $data = $this->postJson("/api/game/{$game['id']}/chapter/advance");
        $this->assertSame('chapter_2', $data['current_phase']);
    }

    public function testChapterAdvanceWithoutRollReturns400(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);

        $this->postJson("/api/game/{$game['id']}/chapter/advance");
        $this->assertSame(400, $this->getLastStatusCode());
    }

    public function testChapterAdvanceInNonChapterPhaseReturns400(): void
    {
        $game = $this->createGame();
        // Still in prologue
        $this->postJson("/api/game/{$game['id']}/chapter/advance");
        $this->assertSame(400, $this->getLastStatusCode());
    }

    // -------------------------------------------------------------------------
    // POST /api/game/{id}/epilogue/book
    // -------------------------------------------------------------------------

    public function testGenerateEpilogueBookReturns200(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        $this->runChapter($game['id'], 'body');
        $this->runChapter($game['id'], 'mind');
        $this->runChapter($game['id'], 'social');

        $this->postJson("/api/game/{$game['id']}/epilogue/book");
        $this->assertSame(200, $this->getLastStatusCode());
    }

    public function testGenerateEpilogueBookInWrongPhaseReturns400(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        // Still in chapter_1; epilogue book should fail
        $this->postJson("/api/game/{$game['id']}/epilogue/book");
        $this->assertSame(400, $this->getLastStatusCode());
    }

    // -------------------------------------------------------------------------
    // POST /api/game/{id}/epilogue/action
    // -------------------------------------------------------------------------

    public function testEpilogueActionReturns200(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        $this->runChapter($game['id'], 'body');
        $this->runChapter($game['id'], 'mind');
        $this->runChapter($game['id'], 'social');

        $this->postJson("/api/game/{$game['id']}/epilogue/book");
        $this->postJson("/api/game/{$game['id']}/epilogue/action", ['attribute' => 'body']);
        $this->assertSame(200, $this->getLastStatusCode());
    }

    public function testEpilogueActionAddsOvercomeScore(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        $this->runChapter($game['id'], 'body');
        $this->runChapter($game['id'], 'mind');
        $this->runChapter($game['id'], 'social');

        $this->postJson("/api/game/{$game['id']}/epilogue/book");
        $data = $this->postJson("/api/game/{$game['id']}/epilogue/action", ['attribute' => 'body']);

        $this->assertGreaterThan(0, $data['game']['overcome_score']);
    }

    public function testEpilogueActionWithInvalidAttributeReturns422(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        $this->runChapter($game['id'], 'body');
        $this->runChapter($game['id'], 'mind');
        $this->runChapter($game['id'], 'social');

        $this->postJson("/api/game/{$game['id']}/epilogue/book");
        $this->postJson("/api/game/{$game['id']}/epilogue/action", ['attribute' => 'bad']);
        $this->assertSame(422, $this->getLastStatusCode());
    }

    // -------------------------------------------------------------------------
    // POST /api/game/{id}/epilogue/final
    // -------------------------------------------------------------------------

    public function testEpilogueFinalReturns200(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        $this->runChapter($game['id'], 'body');
        $this->runChapter($game['id'], 'mind');
        $this->runChapter($game['id'], 'social');
        $this->postJson("/api/game/{$game['id']}/epilogue/book");
        $this->postJson("/api/game/{$game['id']}/epilogue/action", ['attribute' => 'body']);
        $this->postJson("/api/game/{$game['id']}/epilogue/action", ['attribute' => 'mind']);
        $this->postJson("/api/game/{$game['id']}/epilogue/action", ['attribute' => 'social']);

        $this->postJson("/api/game/{$game['id']}/epilogue/final");
        $this->assertSame(200, $this->getLastStatusCode());
    }

    public function testEpilogueFinalSetsGameToCompleted(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        $this->runChapter($game['id'], 'body');
        $this->runChapter($game['id'], 'mind');
        $this->runChapter($game['id'], 'social');
        $this->postJson("/api/game/{$game['id']}/epilogue/book");
        $this->postJson("/api/game/{$game['id']}/epilogue/action", ['attribute' => 'body']);
        $this->postJson("/api/game/{$game['id']}/epilogue/action", ['attribute' => 'mind']);
        $this->postJson("/api/game/{$game['id']}/epilogue/action", ['attribute' => 'social']);

        $data = $this->postJson("/api/game/{$game['id']}/epilogue/final");
        $this->assertSame('completed', $data['game']['current_phase']);
    }

    public function testEpilogueFinalInWrongPhaseReturns400(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        // Still in chapter_1; epilogue/final should fail
        $this->postJson("/api/game/{$game['id']}/epilogue/final");
        $this->assertSame(400, $this->getLastStatusCode());
    }

    // -------------------------------------------------------------------------
    // Full game flow
    // -------------------------------------------------------------------------

    public function testFullGameFlowCompletesSuccessfully(): void
    {
        // 1. Create game
        $game = $this->createGame();
        $this->assertSame('prologue', $game['current_phase']);

        // 2. Complete prologue
        $afterPrologue = $this->completePrologue($game['id']);
        $this->assertSame('chapter_1', $afterPrologue['current_phase']);

        // 3. Chapter 1
        $afterChapter1 = $this->runChapter($game['id'], 'body');
        $this->assertSame('chapter_2', $afterChapter1['game']['current_phase']);

        // 4. Chapter 2
        $afterChapter2 = $this->runChapter($game['id'], 'mind');
        $this->assertSame('chapter_3', $afterChapter2['game']['current_phase']);

        // 5. Chapter 3
        $afterChapter3 = $this->runChapter($game['id'], 'social');
        $this->assertSame('epilogue_action_1', $afterChapter3['game']['current_phase']);

        // 6. Epilogue book
        $epilogueBook = $this->postJson("/api/game/{$game['id']}/epilogue/book");
        $this->assertNotEmpty($epilogueBook['color']);

        // 7. Epilogue action 1
        $action1 = $this->postJson("/api/game/{$game['id']}/epilogue/action", ['attribute' => 'body']);
        $this->assertSame('epilogue_action_2', $action1['game']['current_phase']);
        $this->assertGreaterThan(0, $action1['game']['overcome_score']);

        // 8. Epilogue action 2
        $action2 = $this->postJson("/api/game/{$game['id']}/epilogue/action", ['attribute' => 'mind']);
        $this->assertSame('epilogue_action_3', $action2['game']['current_phase']);
        $this->assertGreaterThan($action1['game']['overcome_score'], $action2['game']['overcome_score']);

        // 9. Epilogue action 3
        $action3 = $this->postJson("/api/game/{$game['id']}/epilogue/action", ['attribute' => 'social']);
        $this->assertSame('epilogue_final', $action3['game']['current_phase']);
        $this->assertGreaterThan($action2['game']['overcome_score'], $action3['game']['overcome_score']);

        // overcome_score must be between 3 and 9 (1-3 per action)
        $this->assertGreaterThanOrEqual(3, $action3['game']['overcome_score']);
        $this->assertLessThanOrEqual(9, $action3['game']['overcome_score']);

        // 10. Final roll
        $final = $this->postJson("/api/game/{$game['id']}/epilogue/final");
        $this->assertSame('completed', $final['game']['current_phase']);
        $this->assertArrayHasKey('outcome', $final['roll_result']);
        $this->assertContains($final['roll_result']['outcome'], ['hit', 'weak_hit', 'miss']);
    }

    // -------------------------------------------------------------------------
    // POST /api/game/{id}/journal
    // -------------------------------------------------------------------------

    public function testCreateJournalEntryReturns201(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        $this->postJson("/api/game/{$game['id']}/journal", ['content' => 'My first log.']);
        $this->assertSame(201, $this->getLastStatusCode());
    }

    public function testCreateJournalEntryResponseHasExpectedFields(): void
    {
        $game  = $this->createGame();
        $this->completePrologue($game['id']);
        $entry = $this->postJson("/api/game/{$game['id']}/journal", ['content' => 'Notes from chapter 1.']);

        $this->assertArrayHasKey('id',         $entry);
        $this->assertArrayHasKey('phase',      $entry);
        $this->assertArrayHasKey('content',    $entry);
        $this->assertArrayHasKey('created_at', $entry);
    }

    public function testCreateJournalEntryStoresContent(): void
    {
        $game    = $this->createGame();
        $this->completePrologue($game['id']);
        $content = 'The library was dark and silent.';
        $entry   = $this->postJson("/api/game/{$game['id']}/journal", ['content' => $content]);

        $this->assertSame($content, $entry['content']);
    }

    public function testCreateJournalEntrySanitizesHtmlContent(): void
    {
        $game  = $this->createGame();
        $this->completePrologue($game['id']);
        $entry = $this->postJson("/api/game/{$game['id']}/journal", [
            'content' => '<b>Bold text</b> and notes.',
        ]);

        $this->assertStringNotContainsString('<b>', $entry['content']);
        $this->assertStringContainsString('Bold text', $entry['content']);
    }

    public function testCreateJournalEntryEmptyContentReturns422(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        $this->postJson("/api/game/{$game['id']}/journal", ['content' => '']);
        $this->assertSame(422, $this->getLastStatusCode());
    }

    public function testCreateJournalEntryMissingContentReturns422(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        $this->postJson("/api/game/{$game['id']}/journal", []);
        $this->assertSame(422, $this->getLastStatusCode());
    }

    // -------------------------------------------------------------------------
    // GET /api/game/{id}/journal
    // -------------------------------------------------------------------------

    public function testJournalListReturns200(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        $this->getJson("/api/game/{$game['id']}/journal");
        $this->assertSame(200, $this->getLastStatusCode());
    }

    public function testJournalListIsEmptyForNewGame(): void
    {
        $game  = $this->createGame();
        $list  = $this->getJson("/api/game/{$game['id']}/journal");
        $this->assertSame([], $list);
    }

    public function testJournalListContainsCreatedEntries(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        $this->postJson("/api/game/{$game['id']}/journal", ['content' => 'Entry 1']);
        $this->postJson("/api/game/{$game['id']}/journal", ['content' => 'Entry 2']);

        $list = $this->getJson("/api/game/{$game['id']}/journal");
        $this->assertCount(2, $list);
    }

    public function testJournalListEntriesContainContent(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        $this->postJson("/api/game/{$game['id']}/journal", ['content' => 'Entry Alpha']);

        $list = $this->getJson("/api/game/{$game['id']}/journal");
        $this->assertSame('Entry Alpha', $list[0]['content']);
    }

    public function testJournalEntryLinkedToBookReturnsBookId(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);

        // Generate chapter book to have a book to link
        $book = $this->postJson("/api/game/{$game['id']}/chapter/book");
        $this->assertNotNull($book['id']);

        $entry = $this->postJson("/api/game/{$game['id']}/journal", [
            'content' => 'Studied the ancient tome.',
            'book_id' => $book['id'],
        ]);
        $this->assertSame(201, $this->getLastStatusCode());
        $this->assertSame($book['id'], $entry['book_id']);
    }

    public function testJournalEntryLinkedToNonExistentBookReturns404(): void
    {
        $game = $this->createGame();
        $this->completePrologue($game['id']);
        $this->postJson("/api/game/{$game['id']}/journal", [
            'content' => 'A note.',
            'book_id' => 99999999,
        ]);
        $this->assertSame(404, $this->getLastStatusCode());
    }

    // -------------------------------------------------------------------------
    // GET /api/oracle/tables
    // -------------------------------------------------------------------------

    public function testOracleTablesReturns200(): void
    {
        $this->getJson('/api/oracle/tables');
        $this->assertSame(200, $this->getLastStatusCode());
    }

    public function testOracleTablesResponseHasAllSixTables(): void
    {
        $data = $this->getJson('/api/oracle/tables');

        $this->assertArrayHasKey('genre',    $data);
        $this->assertArrayHasKey('epoch',    $data);
        $this->assertArrayHasKey('color',    $data);
        $this->assertArrayHasKey('binding',  $data);
        $this->assertArrayHasKey('smell',    $data);
        $this->assertArrayHasKey('interior', $data);
    }

    public function testOracleTablesEachTableHasSixEntries(): void
    {
        $data = $this->getJson('/api/oracle/tables');

        foreach (['genre', 'epoch', 'color', 'binding', 'smell', 'interior'] as $tableName) {
            $this->assertCount(6, $data[$tableName], "Table '{$tableName}' must have 6 entries");
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/oracle/random-setting
    // -------------------------------------------------------------------------

    public function testOracleRandomSettingReturns200(): void
    {
        $this->getJson('/api/oracle/random-setting');
        $this->assertSame(200, $this->getLastStatusCode());
    }

    public function testOracleRandomSettingResponseHasGenreAndEpoch(): void
    {
        $data = $this->getJson('/api/oracle/random-setting');

        $this->assertArrayHasKey('genre', $data);
        $this->assertArrayHasKey('epoch', $data);
    }

    public function testOracleRandomSettingGenreHasValueAndHintKeys(): void
    {
        $data = $this->getJson('/api/oracle/random-setting');

        $this->assertArrayHasKey('value', $data['genre']);
        $this->assertArrayHasKey('hint',  $data['genre']);
    }

    public function testOracleRandomSettingEpochHasValueAndHintKeys(): void
    {
        $data = $this->getJson('/api/oracle/random-setting');

        $this->assertArrayHasKey('value', $data['epoch']);
        $this->assertArrayHasKey('hint',  $data['epoch']);
    }

    public function testOracleRandomSettingGenreValueIsFromTable(): void
    {
        $tables      = $this->getJson('/api/oracle/tables');
        $validGenres = array_column($tables['genre'], 'value');

        $setting = $this->getJson('/api/oracle/random-setting');
        $this->assertContains($setting['genre']['value'], $validGenres);
    }

    public function testOracleRandomSettingEpochValueIsFromTable(): void
    {
        $tables      = $this->getJson('/api/oracle/tables');
        $validEpochs = array_column($tables['epoch'], 'value');

        $setting = $this->getJson('/api/oracle/random-setting');
        $this->assertContains($setting['epoch']['value'], $validEpochs);
    }
}
