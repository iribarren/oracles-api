<?php

declare(strict_types=1);

namespace App\Tests\Behat\Steps;

/**
 * Journal steps: saving narrative entries (with optional book link) and listing them.
 *
 * A journal entry's content is required and sanitized; an attached book_id must
 * belong to the same game. These steps cover those rules and ownership.
 */
trait JournalSteps
{
    /**
     * Generates a book on the current game and remembers its id (valid link).
     *
     * @Given the game has a book
     */
    public function theGameHasABook(): void
    {
        $book = $this->gameEngine->generateChapterBook($this->refetchGame());
        $this->lastBookId = $book->getId();
    }

    /**
     * Creates a separate game, generates a book on it, and remembers that id —
     * a book that does NOT belong to the game under test.
     *
     * @Given another game has a book
     */
    public function anotherGameHasABook(): void
    {
        $other = $this->gameEngine->createGame('aventura_rapida', null);
        $this->gameEngine->completePrologue($other, 'Other Hero', 'Other.', 'Investigación', 'Contemporanea');
        $book = $this->gameEngine->generateChapterBook($other);
        $this->foreignBookId = $book->getId();
    }

    /**
     * @Given a game owned by another player
     */
    public function aGameOwnedByAnotherPlayer(): void
    {
        $other = $this->createPlayer('owner-other@biblioteca.test');
        $game  = $this->gameEngine->createGame('aventura_rapida', $other);
        $this->gameEngine->completePrologue($game, 'Owned Hero', 'Owned.', 'Investigación', 'Contemporanea');
        $this->gameUuid = $game->getId();
    }

    /**
     * @When I save a journal entry :content
     */
    public function iSaveAJournalEntry(string $content): void
    {
        $this->sendGameRequest('POST', '/journal', ['content' => $content]);
    }

    /**
     * @When I save an empty journal entry
     */
    public function iSaveAnEmptyJournalEntry(): void
    {
        $this->sendGameRequest('POST', '/journal', ['content' => '']);
    }

    /**
     * @When I save a journal entry :content linked to the book
     */
    public function iSaveAJournalEntryLinkedToTheBook(string $content): void
    {
        $this->sendGameRequest('POST', '/journal', ['content' => $content, 'book_id' => $this->lastBookId]);
    }

    /**
     * @When I save a journal entry :content linked to book :bookId
     */
    public function iSaveAJournalEntryLinkedToBook(string $content, int $bookId): void
    {
        $this->sendGameRequest('POST', '/journal', ['content' => $content, 'book_id' => $bookId]);
    }

    /**
     * @When I save a journal entry :content linked to the foreign book
     */
    public function iSaveAJournalEntryLinkedToTheForeignBook(string $content): void
    {
        $this->sendGameRequest('POST', '/journal', ['content' => $content, 'book_id' => $this->foreignBookId]);
    }

    /**
     * @When I list the journal
     */
    public function iListTheJournal(): void
    {
        $this->sendGameRequest('GET', '/journal');
    }

    /**
     * @Then I see :count journal entries
     */
    public function iSeeJournalEntries(int $count): void
    {
        $data = $this->getDecodedResponse();
        if (\count($data) !== $count) {
            throw new \RuntimeException(\sprintf('Expected %d journal entries but got %d.', $count, \count($data)));
        }
    }

    /**
     * @Then the journal entries are in chronological order
     */
    public function theJournalEntriesAreInChronologicalOrder(): void
    {
        $data      = $this->getDecodedResponse();
        $timestamps = \array_column($data, 'created_at');
        $sorted     = $timestamps;
        \sort($sorted);
        if ($timestamps !== $sorted) {
            throw new \RuntimeException('Journal entries are not in chronological (ascending) order.');
        }
    }
}
