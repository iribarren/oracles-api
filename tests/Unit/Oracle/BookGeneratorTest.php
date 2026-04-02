<?php

declare(strict_types=1);

namespace App\Tests\Unit\Oracle;

use App\Entity\Book;
use App\Entity\GameSession;
use App\Enum\GamePhase;
use App\Oracle\BookGenerator;
use App\Oracle\OracleService;
use App\Repository\OracleCategoryRepository;
use PHPUnit\Framework\TestCase;

class BookGeneratorTest extends TestCase
{
    private BookGenerator $bookGenerator;
    private GameSession   $gameSession;

    protected function setUp(): void
    {
        $this->bookGenerator = new BookGenerator(new OracleService($this->createMock(OracleCategoryRepository::class)));
        $this->gameSession   = new GameSession();
    }

    public function testGenerateBookReturnsBookInstance(): void
    {
        $book = $this->bookGenerator->generateBook($this->gameSession, GamePhase::CHAPTER_1);

        $this->assertInstanceOf(Book::class, $book);
    }

    public function testGenerateBookSetsGameSession(): void
    {
        $book = $this->bookGenerator->generateBook($this->gameSession, GamePhase::CHAPTER_1);

        $this->assertSame($this->gameSession, $book->getGameSession());
    }

    public function testGenerateBookSetsPhase(): void
    {
        $book = $this->bookGenerator->generateBook($this->gameSession, GamePhase::CHAPTER_2);

        $this->assertSame(GamePhase::CHAPTER_2, $book->getPhase());
    }

    public function testGenerateBookSetsEpiloguePhase(): void
    {
        $book = $this->bookGenerator->generateBook($this->gameSession, GamePhase::EPILOGUE_ACTION_1);

        $this->assertSame(GamePhase::EPILOGUE_ACTION_1, $book->getPhase());
    }

    public function testGenerateBookColorIsNonEmptyString(): void
    {
        $book = $this->bookGenerator->generateBook($this->gameSession, GamePhase::CHAPTER_1);

        $this->assertNotEmpty($book->getColor());
    }

    public function testGenerateBookBindingIsNonEmptyString(): void
    {
        $book = $this->bookGenerator->generateBook($this->gameSession, GamePhase::CHAPTER_1);

        $this->assertNotEmpty($book->getBinding());
    }

    public function testGenerateBookSmellIsNonEmptyString(): void
    {
        $book = $this->bookGenerator->generateBook($this->gameSession, GamePhase::CHAPTER_1);

        $this->assertNotEmpty($book->getSmell());
    }

    public function testGenerateBookInteriorIsNonEmptyString(): void
    {
        $book = $this->bookGenerator->generateBook($this->gameSession, GamePhase::CHAPTER_1);

        $this->assertNotEmpty($book->getInterior());
    }

    public function testGenerateBookColorValueComesFromColorTable(): void
    {
        $oracleService = new OracleService($this->createMock(OracleCategoryRepository::class));
        $validColors   = array_column($oracleService->getColorTable(), 'value');

        $book = $this->bookGenerator->generateBook($this->gameSession, GamePhase::CHAPTER_1);

        $this->assertContains($book->getColor(), $validColors);
    }

    public function testGenerateBookBindingValueComesFromBindingTable(): void
    {
        $oracleService  = new OracleService($this->createMock(OracleCategoryRepository::class));
        $validBindings  = array_column($oracleService->getBindingTable(), 'value');

        $book = $this->bookGenerator->generateBook($this->gameSession, GamePhase::CHAPTER_1);

        $this->assertContains($book->getBinding(), $validBindings);
    }

    public function testGenerateBookSmellValueComesFromSmellTable(): void
    {
        $oracleService = new OracleService($this->createMock(OracleCategoryRepository::class));
        $validSmells   = array_column($oracleService->getSmellTable(), 'value');

        $book = $this->bookGenerator->generateBook($this->gameSession, GamePhase::CHAPTER_1);

        $this->assertContains($book->getSmell(), $validSmells);
    }

    public function testGenerateBookInteriorValueComesFromInteriorTable(): void
    {
        $oracleService  = new OracleService($this->createMock(OracleCategoryRepository::class));
        $validInteriors = array_column($oracleService->getInteriorTable(), 'value');

        $book = $this->bookGenerator->generateBook($this->gameSession, GamePhase::CHAPTER_1);

        $this->assertContains($book->getInterior(), $validInteriors);
    }

    public function testGenerateBookColorHintIsString(): void
    {
        $book = $this->bookGenerator->generateBook($this->gameSession, GamePhase::CHAPTER_1);

        $this->assertIsString($book->getColorHint());
    }

    public function testGenerateBookBindingHintIsString(): void
    {
        $book = $this->bookGenerator->generateBook($this->gameSession, GamePhase::CHAPTER_1);

        $this->assertIsString($book->getBindingHint());
    }

    public function testGenerateBookSmellHintIsString(): void
    {
        $book = $this->bookGenerator->generateBook($this->gameSession, GamePhase::CHAPTER_1);

        $this->assertIsString($book->getSmellHint());
    }

    public function testGenerateBookIdIsNullBeforePersistence(): void
    {
        $book = $this->bookGenerator->generateBook($this->gameSession, GamePhase::CHAPTER_1);

        // Entity has not been persisted, so id should be null
        $this->assertNull($book->getId());
    }

    public function testGenerateBookProducesVariedBooksOverMultipleCalls(): void
    {
        $colors = [];
        for ($i = 0; $i < 100; $i++) {
            $book     = $this->bookGenerator->generateBook($this->gameSession, GamePhase::CHAPTER_1);
            $colors[] = $book->getColor();
        }

        $this->assertGreaterThan(
            1,
            count(array_unique($colors)),
            'Multiple calls should produce varied book colors'
        );
    }
}
