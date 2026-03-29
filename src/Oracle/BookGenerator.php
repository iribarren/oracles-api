<?php

declare(strict_types=1);

namespace App\Oracle;

use App\Entity\Book;
use App\Entity\GameSession;
use App\Enum\GamePhase;

class BookGenerator
{
    public function __construct(
        private readonly OracleService $oracleService,
    ) {}

    /**
     * Creates a Book entity with all properties filled from oracle tables.
     * The entity is not persisted; the caller is responsible for persistence.
     */
    public function generateBook(GameSession $gameSession, GamePhase $phase): Book
    {
        $color   = $this->oracleService->getRandomFromTable($this->oracleService->getColorTable());
        $binding = $this->oracleService->getRandomFromTable($this->oracleService->getBindingTable());
        $smell   = $this->oracleService->getRandomFromTable($this->oracleService->getSmellTable());
        $interior = $this->oracleService->getRandomFromTable($this->oracleService->getInteriorTable());

        $book = new Book();
        $book->setGameSession($gameSession);
        $book->setPhase($phase);
        $book->setColor($color['value']);
        $book->setColorHint($color['hint']);
        $book->setBinding($binding['value']);
        $book->setBindingHint($binding['hint']);
        $book->setSmell($smell['value']);
        $book->setSmellHint($smell['hint']);
        $book->setInterior($interior['value']);

        return $book;
    }
}
