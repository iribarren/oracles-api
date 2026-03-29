<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\GamePhase;
use PHPUnit\Framework\TestCase;

class GamePhaseTest extends TestCase
{
    public function testChapter1IsChapter(): void
    {
        $this->assertTrue(GamePhase::CHAPTER_1->isChapter());
    }

    public function testChapter2IsChapter(): void
    {
        $this->assertTrue(GamePhase::CHAPTER_2->isChapter());
    }

    public function testChapter3IsChapter(): void
    {
        $this->assertTrue(GamePhase::CHAPTER_3->isChapter());
    }

    public function testPrologueIsNotChapter(): void
    {
        $this->assertFalse(GamePhase::PROLOGUE->isChapter());
    }

    public function testEpilogueAction1IsNotChapter(): void
    {
        $this->assertFalse(GamePhase::EPILOGUE_ACTION_1->isChapter());
    }

    public function testEpilogueAction2IsNotChapter(): void
    {
        $this->assertFalse(GamePhase::EPILOGUE_ACTION_2->isChapter());
    }

    public function testEpilogueAction3IsNotChapter(): void
    {
        $this->assertFalse(GamePhase::EPILOGUE_ACTION_3->isChapter());
    }

    public function testEpilogueFinalIsNotChapter(): void
    {
        $this->assertFalse(GamePhase::EPILOGUE_FINAL->isChapter());
    }

    public function testCompletedIsNotChapter(): void
    {
        $this->assertFalse(GamePhase::COMPLETED->isChapter());
    }

    public function testEpilogueAction1IsEpilogueAction(): void
    {
        $this->assertTrue(GamePhase::EPILOGUE_ACTION_1->isEpilogueAction());
    }

    public function testEpilogueAction2IsEpilogueAction(): void
    {
        $this->assertTrue(GamePhase::EPILOGUE_ACTION_2->isEpilogueAction());
    }

    public function testEpilogueAction3IsEpilogueAction(): void
    {
        $this->assertTrue(GamePhase::EPILOGUE_ACTION_3->isEpilogueAction());
    }

    public function testPrologueIsNotEpilogueAction(): void
    {
        $this->assertFalse(GamePhase::PROLOGUE->isEpilogueAction());
    }

    public function testChapter1IsNotEpilogueAction(): void
    {
        $this->assertFalse(GamePhase::CHAPTER_1->isEpilogueAction());
    }

    public function testChapter2IsNotEpilogueAction(): void
    {
        $this->assertFalse(GamePhase::CHAPTER_2->isEpilogueAction());
    }

    public function testChapter3IsNotEpilogueAction(): void
    {
        $this->assertFalse(GamePhase::CHAPTER_3->isEpilogueAction());
    }

    public function testEpilogueFinalIsNotEpilogueAction(): void
    {
        $this->assertFalse(GamePhase::EPILOGUE_FINAL->isEpilogueAction());
    }

    public function testCompletedIsNotEpilogueAction(): void
    {
        $this->assertFalse(GamePhase::COMPLETED->isEpilogueAction());
    }

    public function testNextFromPrologueIsChapter1(): void
    {
        $this->assertSame(GamePhase::CHAPTER_1, GamePhase::PROLOGUE->next());
    }

    public function testNextFromChapter1IsChapter2(): void
    {
        $this->assertSame(GamePhase::CHAPTER_2, GamePhase::CHAPTER_1->next());
    }

    public function testNextFromChapter2IsChapter3(): void
    {
        $this->assertSame(GamePhase::CHAPTER_3, GamePhase::CHAPTER_2->next());
    }

    public function testNextFromChapter3IsEpilogueAction1(): void
    {
        $this->assertSame(GamePhase::EPILOGUE_ACTION_1, GamePhase::CHAPTER_3->next());
    }

    public function testNextFromEpilogueAction1IsEpilogueAction2(): void
    {
        $this->assertSame(GamePhase::EPILOGUE_ACTION_2, GamePhase::EPILOGUE_ACTION_1->next());
    }

    public function testNextFromEpilogueAction2IsEpilogueAction3(): void
    {
        $this->assertSame(GamePhase::EPILOGUE_ACTION_3, GamePhase::EPILOGUE_ACTION_2->next());
    }

    public function testNextFromEpilogueAction3IsEpilogueFinal(): void
    {
        $this->assertSame(GamePhase::EPILOGUE_FINAL, GamePhase::EPILOGUE_ACTION_3->next());
    }

    public function testNextFromEpilogueFinalIsCompleted(): void
    {
        $this->assertSame(GamePhase::COMPLETED, GamePhase::EPILOGUE_FINAL->next());
    }

    public function testNextFromCompletedStaysCompleted(): void
    {
        $this->assertSame(GamePhase::COMPLETED, GamePhase::COMPLETED->next());
    }

    public function testFullNextChainFromPrologueToCompleted(): void
    {
        $expectedChain = [
            GamePhase::PROLOGUE,
            GamePhase::CHAPTER_1,
            GamePhase::CHAPTER_2,
            GamePhase::CHAPTER_3,
            GamePhase::EPILOGUE_ACTION_1,
            GamePhase::EPILOGUE_ACTION_2,
            GamePhase::EPILOGUE_ACTION_3,
            GamePhase::EPILOGUE_FINAL,
            GamePhase::COMPLETED,
        ];

        $current = GamePhase::PROLOGUE;
        foreach ($expectedChain as $expected) {
            $this->assertSame($expected, $current);
            $current = $current->next();
        }

        // After COMPLETED, stays at COMPLETED
        $this->assertSame(GamePhase::COMPLETED, $current);
    }

    public function testGamePhaseValuesAreCorrect(): void
    {
        $this->assertSame('prologue',           GamePhase::PROLOGUE->value);
        $this->assertSame('chapter_1',          GamePhase::CHAPTER_1->value);
        $this->assertSame('chapter_2',          GamePhase::CHAPTER_2->value);
        $this->assertSame('chapter_3',          GamePhase::CHAPTER_3->value);
        $this->assertSame('epilogue_action_1',  GamePhase::EPILOGUE_ACTION_1->value);
        $this->assertSame('epilogue_action_2',  GamePhase::EPILOGUE_ACTION_2->value);
        $this->assertSame('epilogue_action_3',  GamePhase::EPILOGUE_ACTION_3->value);
        $this->assertSame('epilogue_final',     GamePhase::EPILOGUE_FINAL->value);
        $this->assertSame('completed',          GamePhase::COMPLETED->value);
    }
}
