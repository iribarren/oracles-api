<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Book;
use App\Entity\GameSession;
use App\Entity\JournalEntry;
use App\Entity\RollResult;
use App\Entity\User;
use App\Enum\AttributeType;
use App\Enum\GamePhase;
use App\Enum\RollOutcome;
use App\Oracle\BookGenerator;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use LogicException;

class GameEngine
{
    public function __construct(
        private readonly DiceService            $diceService,
        private readonly BookGenerator          $bookGenerator,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * Creates a new game session in PROLOGUE phase with three default attributes.
     */
    public function createGame(?User $owner = null): GameSession
    {
        $game = new GameSession();

        foreach (AttributeType::cases() as $type) {
            $attribute = new Attribute();
            $attribute->setType($type);
            $attribute->setBaseValue(1);
            $game->addAttribute($attribute);
        }

        if ($owner !== null) {
            $game->setOwner($owner);
        }

        $this->entityManager->persist($game);
        $this->entityManager->flush();

        return $game;
    }

    /**
     * Finalises the prologue by setting character info and advancing to CHAPTER_1.
     */
    public function completePrologue(
        GameSession $game,
        string      $characterName,
        string      $characterDescription,
        string      $genre,
        string      $epoch,
    ): void {
        $this->assertPhase($game, GamePhase::PROLOGUE);

        $game->setCharacterName($characterName);
        $game->setCharacterDescription($characterDescription);
        $game->setGenre($genre);
        $game->setEpoch($epoch);
        $game->setCurrentPhase(GamePhase::CHAPTER_1);

        $this->entityManager->flush();
    }

    /**
     * Generates and persists a book for the current chapter phase.
     */
    public function generateChapterBook(GameSession $game): Book
    {
        if (!$game->getCurrentPhase()->isChapter()) {
            throw new LogicException(
                \sprintf('Cannot generate chapter book in phase "%s".', $game->getCurrentPhase()->value)
            );
        }

        $book = $this->bookGenerator->generateBook($game, $game->getCurrentPhase());
        $game->addBook($book);
        $this->entityManager->flush();

        return $book;
    }

    /**
     * Resolves a chapter by rolling the chosen attribute.
     * Each attribute may only be used once across the three chapters.
     *
     * Outcome effects:
     *   HIT      → attribute.background += 1
     *   WEAK_HIT → attribute.support   += 1
     *   MISS     → attribute.background -= 1
     */
    public function resolveChapter(GameSession $game, AttributeType $chosenAttribute): RollResult
    {
        if (!$game->getCurrentPhase()->isChapter()) {
            throw new LogicException(
                \sprintf('Cannot resolve chapter in phase "%s".', $game->getCurrentPhase()->value)
            );
        }

        $this->assertAttributeNotUsedIn(
            $game,
            $chosenAttribute,
            [GamePhase::CHAPTER_1, GamePhase::CHAPTER_2, GamePhase::CHAPTER_3]
        );

        $attribute = $this->getAttributeByType($game, $chosenAttribute);
        $modifier  = $attribute->getBaseValue() + $attribute->getBackground() + $attribute->getSupport();

        $rollResult = $this->diceService->rollAction($modifier);
        $rollResult->setPhase($game->getCurrentPhase());
        $rollResult->setAttributeType($chosenAttribute);

        match ($rollResult->getOutcome()) {
            RollOutcome::HIT      => $attribute->setBackground($attribute->getBackground() + 1),
            RollOutcome::WEAK_HIT => $attribute->setSupport($attribute->getSupport() + 1),
            RollOutcome::MISS     => $attribute->setBackground($attribute->getBackground() - 1),
        };

        $game->setCurrentPhase($game->getCurrentPhase()->next());
        $game->addRollResult($rollResult);

        $this->entityManager->flush();

        return $rollResult;
    }

    /**
     * Generates and persists the single book used during the entire epilogue.
     * Must be called when the game is in EPILOGUE_ACTION_1.
     */
    public function generateEpilogueBook(GameSession $game): Book
    {
        $this->assertPhase($game, GamePhase::EPILOGUE_ACTION_1);

        $book = $this->bookGenerator->generateBook($game, $game->getCurrentPhase());
        $game->addBook($book);
        $this->entityManager->flush();

        return $book;
    }

    /**
     * Resolves one epilogue action roll.
     *
     * Modifier = base_value + background (support from chapters always applies).
     * Optionally a support attribute can be used — but only ONCE across all three
     * epilogue actions (tracked via game.support_used). The support value of that
     * attribute is added to the modifier.
     *
     * Outcome scoring added to game.overcome_score:
     *   HIT      → +3
     *   WEAK_HIT → +2
     *   MISS     → +1
     */
    public function resolveEpilogueAction(
        GameSession    $game,
        AttributeType  $chosenAttribute,
        ?AttributeType $supportAttribute = null,
    ): RollResult {
        if (!$game->getCurrentPhase()->isEpilogueAction()) {
            throw new LogicException(
                \sprintf('Cannot resolve epilogue action in phase "%s".', $game->getCurrentPhase()->value)
            );
        }

        $this->assertAttributeNotUsedIn(
            $game,
            $chosenAttribute,
            [GamePhase::EPILOGUE_ACTION_1, GamePhase::EPILOGUE_ACTION_2, GamePhase::EPILOGUE_ACTION_3]
        );

        $attribute = $this->getAttributeByType($game, $chosenAttribute);
        $modifier  = $attribute->getBaseValue() + $attribute->getBackground();

        if ($supportAttribute !== null) {
            if ($game->isSupportUsed()) {
                throw new LogicException('Support bonus has already been used in this epilogue.');
            }

            $supportAttr = $this->getAttributeByType($game, $supportAttribute);
            if ($supportAttr->getSupport() > 0) {
                $modifier += $supportAttr->getSupport();
                $game->setSupportUsed(true);
            }
        }

        $actionNumber = match ($game->getCurrentPhase()) {
            GamePhase::EPILOGUE_ACTION_1 => 1,
            GamePhase::EPILOGUE_ACTION_2 => 2,
            GamePhase::EPILOGUE_ACTION_3 => 3,
            default => throw new LogicException('Unexpected epilogue phase.'),
        };

        $rollResult = $this->diceService->rollAction($modifier);
        $rollResult->setPhase($game->getCurrentPhase());
        $rollResult->setAttributeType($chosenAttribute);
        $rollResult->setActionNumber($actionNumber);

        $overcomePoints = match ($rollResult->getOutcome()) {
            RollOutcome::HIT      => 3,
            RollOutcome::WEAK_HIT => 2,
            RollOutcome::MISS     => 1,
        };

        $game->setOvercomeScore($game->getOvercomeScore() + $overcomePoints);
        $game->setCurrentPhase($game->getCurrentPhase()->next());
        $game->addRollResult($rollResult);

        $this->entityManager->flush();

        return $rollResult;
    }

    /**
     * Resolves the final epilogue roll.
     *
     * No action die is rolled. action_score = accumulated overcome_score.
     * Two d10s are rolled normally. Sets phase to COMPLETED.
     */
    public function resolveFinalRoll(GameSession $game): RollResult
    {
        $this->assertPhase($game, GamePhase::EPILOGUE_FINAL);

        $challengeDie1 = $this->diceService->rollDie(10);
        $challengeDie2 = $this->diceService->rollDie(10);
        $actionScore   = $game->getOvercomeScore();

        $outcome = match (true) {
            $actionScore >= $challengeDie1 && $actionScore >= $challengeDie2 => RollOutcome::HIT,
            $actionScore >= $challengeDie1 || $actionScore >= $challengeDie2 => RollOutcome::WEAK_HIT,
            default => RollOutcome::MISS,
        };

        $rollResult = new RollResult();
        // action_die = 0 signals that no d6 was rolled (final roll mechanic)
        $rollResult->setActionDie(0);
        $rollResult->setChallengeDie1($challengeDie1);
        $rollResult->setChallengeDie2($challengeDie2);
        $rollResult->setModifier(0);
        $rollResult->setActionScore($actionScore);
        $rollResult->setOutcome($outcome);
        $rollResult->setPhase(GamePhase::EPILOGUE_FINAL);

        $game->setCurrentPhase(GamePhase::COMPLETED);
        $game->addRollResult($rollResult);

        $this->entityManager->flush();

        return $rollResult;
    }

    /**
     * Creates and persists a journal entry linked to the current phase.
     */
    public function saveJournalEntry(GameSession $game, string $content, ?Book $book = null): JournalEntry
    {
        $entry = new JournalEntry();
        $entry->setPhase($game->getCurrentPhase());
        $entry->setContent($content);

        if ($book !== null) {
            $entry->setBook($book);
        }

        $game->addJournalEntry($entry);
        $this->entityManager->flush();

        return $entry;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function assertPhase(GameSession $game, GamePhase $expected): void
    {
        if ($game->getCurrentPhase() !== $expected) {
            throw new LogicException(
                \sprintf(
                    'Expected phase "%s" but game is in phase "%s".',
                    $expected->value,
                    $game->getCurrentPhase()->value,
                )
            );
        }
    }

    private function getAttributeByType(GameSession $game, AttributeType $type): Attribute
    {
        foreach ($game->getAttributes() as $attribute) {
            if ($attribute->getType() === $type) {
                return $attribute;
            }
        }

        throw new InvalidArgumentException(
            \sprintf('Attribute of type "%s" not found in game session.', $type->value)
        );
    }

    /**
     * Throws if the given attribute type was already used in any of the specified phases.
     *
     * @param GamePhase[] $phases
     */
    private function assertAttributeNotUsedIn(GameSession $game, AttributeType $type, array $phases): void
    {
        foreach ($game->getRollResults() as $result) {
            if (!\in_array($result->getPhase(), $phases, true)) {
                continue;
            }
            if ($result->getAttributeType() === $type) {
                throw new LogicException(
                    \sprintf('Attribute "%s" has already been used in one of these phases.', $type->value)
                );
            }
        }
    }
}
