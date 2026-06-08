<?php

declare(strict_types=1);

namespace App\Tests\Behat\Steps;

use App\Enum\GamePhase;

/**
 * Game-mechanics steps (Batch 1): prologue, chapters, epilogue, dice control.
 *
 * The steps arrange state through GameEngine and act through the real API, so the
 * scenarios read like the rulebook while exercising the actual endpoints. Shared
 * plumbing (sendGameRequest, refetchGame, dice queue…) lives on the host context.
 */
trait GameSteps
{
    // -------------------------------------------------------------------------
    // Arrange (set up state through GameEngine / Doctrine)
    // -------------------------------------------------------------------------

    /**
     * @Given a new game
     */
    public function aNewGame(): void
    {
        $game = $this->gameEngine->createGame('aventura_rapida', $this->currentUser);
        $this->gameUuid = $game->getId();
    }

    /**
     * Positions a fresh game directly at the requested phase. The prologue is
     * completed (so character data exists and we leave PROLOGUE), then the phase
     * is set straight to the target — scenarios add their own rolls via When steps.
     *
     * @Given a game positioned at phase :phase
     */
    public function aGamePositionedAtPhase(string $phase): void
    {
        $target = GamePhase::from($phase);
        $game   = $this->gameEngine->createGame('aventura_rapida', $this->currentUser);
        $this->gameUuid = $game->getId();

        if ($target === GamePhase::PROLOGUE) {
            return;
        }

        // Leave the prologue with placeholder character data.
        $this->gameEngine->completePrologue($game, 'Test Hero', 'A test character.', 'Investigación', 'Contemporanea');

        if ($target !== GamePhase::CHAPTER_1) {
            $game->setCurrentPhase($target);
            $this->entityManager->flush();
        }
    }

    /**
     * @Given the :attr attribute has background :background and support :support
     */
    public function theAttributeHasBackgroundAndSupport(string $attr, int $background, int $support): void
    {
        $attribute = $this->getAttribute($attr);
        $attribute->setBackground($background);
        $attribute->setSupport($support);
        $this->entityManager->flush();
    }

    /**
     * @Given the game overcome score is :value
     */
    public function theGameOvercomeScoreIs(int $value): void
    {
        $game = $this->refetchGame();
        $game->setOvercomeScore($value);
        $this->entityManager->flush();
    }

    // -------------------------------------------------------------------------
    // Dice control (queue deterministic die faces)
    // -------------------------------------------------------------------------

    /**
     * Queues the three faces consumed by an action roll, in engine order:
     * action die (d6), challenge die 1 (d10), challenge die 2 (d10).
     *
     * @Given the next action roll is action die :d6 and challenge dice :c1 and :c2
     */
    public function theNextActionRollIs(int $d6, int $c1, int $c2): void
    {
        $this->dice->push($d6, $c1, $c2);
    }

    /**
     * Readable shortcut for the common outcomes. The queued faces guarantee the
     * outcome for the small non-negative modifiers seen in early phases:
     *   hit      → score beats both minimum challenge dice
     *   weak_hit → score beats one die, loses the other
     *   miss     → score loses to both maximum challenge dice
     *
     * @Given the next action roll is a :outcome
     */
    public function theNextActionRollIsA(string $outcome): void
    {
        match ($outcome) {
            'hit'      => $this->dice->push(6, 1, 1),
            'weak_hit' => $this->dice->push(1, 1, 10),
            'miss'     => $this->dice->push(1, 10, 10),
            default    => throw new \InvalidArgumentException(\sprintf('Unknown outcome "%s".', $outcome)),
        };
    }

    /**
     * Queues the two d10 faces for the final roll (no action die is rolled;
     * action_score is the accumulated overcome score).
     *
     * @Given the next final roll challenge dice are :c1 and :c2
     */
    public function theNextFinalRollChallengeDiceAre(int $c1, int $c2): void
    {
        $this->dice->push($c1, $c2);
    }

    // -------------------------------------------------------------------------
    // Act (exercise the real API endpoints)
    // -------------------------------------------------------------------------

    /**
     * @When I complete the prologue with name :name genre :genre epoch :epoch
     */
    public function iCompleteThePrologue(string $name, string $genre, string $epoch): void
    {
        $this->sendGameRequest('POST', '/prologue', [
            'character_name'        => $name,
            'character_description' => 'Written during the prologue.',
            'genre'                 => $genre,
            'epoch'                 => $epoch,
        ]);
    }

    /**
     * @When I complete the prologue without a name
     */
    public function iCompleteThePrologueWithoutAName(): void
    {
        $this->sendGameRequest('POST', '/prologue', [
            'character_description' => 'No name provided.',
            'genre'                 => 'Investigación',
            'epoch'                 => 'Contemporanea',
        ]);
    }

    /**
     * @When I generate the chapter book
     */
    public function iGenerateTheChapterBook(): void
    {
        $this->sendGameRequest('POST', '/chapter/book');
    }

    /**
     * @When I resolve the chapter using :attr
     */
    public function iResolveTheChapterUsing(string $attr): void
    {
        $this->sendGameRequest('POST', '/chapter/roll', ['attribute' => $attr]);
    }

    /**
     * @When I advance the chapter
     */
    public function iAdvanceTheChapter(): void
    {
        $this->sendGameRequest('POST', '/chapter/advance');
    }

    /**
     * @When I set the support title :title for :attr
     */
    public function iSetTheSupportTitleFor(string $title, string $attr): void
    {
        $this->sendGameRequest('POST', '/chapter/support-title', [
            'attribute'     => $attr,
            'support_title' => $title,
        ]);
    }

    /**
     * @When I generate the epilogue book
     */
    public function iGenerateTheEpilogueBook(): void
    {
        $this->sendGameRequest('POST', '/epilogue/book');
    }

    /**
     * @When I advance the epilogue
     */
    public function iAdvanceTheEpilogue(): void
    {
        $this->sendGameRequest('POST', '/epilogue/advance');
    }

    /**
     * @When I resolve the epilogue action using :attr
     */
    public function iResolveTheEpilogueActionUsing(string $attr): void
    {
        $this->sendGameRequest('POST', '/epilogue/action', ['attribute' => $attr]);
    }

    /**
     * @When I resolve the epilogue action using :attr with support :supportAttr
     */
    public function iResolveTheEpilogueActionUsingWithSupport(string $attr, string $supportAttr): void
    {
        $this->sendGameRequest('POST', '/epilogue/action', [
            'attribute'         => $attr,
            'support_attribute' => $supportAttr,
        ]);
    }

    /**
     * @When I roll the final outcome
     */
    public function iRollTheFinalOutcome(): void
    {
        $this->sendGameRequest('POST', '/epilogue/final');
    }

    // -------------------------------------------------------------------------
    // Assert (read the rules back from persisted state / responses)
    // -------------------------------------------------------------------------

    /**
     * @Then the :attr attribute background is :value
     */
    public function theAttributeBackgroundIs(string $attr, int $value): void
    {
        $this->assertSameInt($value, $this->refetchAttribute($attr)->getBackground(), \sprintf('%s background', $attr));
    }

    /**
     * @Then the :attr attribute support is :value
     */
    public function theAttributeSupportIs(string $attr, int $value): void
    {
        $this->assertSameInt($value, $this->refetchAttribute($attr)->getSupport(), \sprintf('%s support', $attr));
    }

    /**
     * @Then the :attr attribute support title is :title
     */
    public function theAttributeSupportTitleIs(string $attr, string $title): void
    {
        $actual = (string) $this->refetchAttribute($attr)->getSupportTitle();
        if ($actual !== $title) {
            throw new \RuntimeException(\sprintf('Expected %s support title "%s" but got "%s".', $attr, $title, $actual));
        }
    }

    /**
     * @Then the overcome score is :value
     */
    public function theOvercomeScoreIs(int $value): void
    {
        $this->assertSameInt($value, $this->refetchGame()->getOvercomeScore(), 'overcome score');
    }

    /**
     * @Then the game phase is :phase
     */
    public function theGamePhaseIs(string $phase): void
    {
        $actual = $this->refetchGame()->getCurrentPhase()->value;
        if ($actual !== $phase) {
            throw new \RuntimeException(\sprintf('Expected game phase "%s" but got "%s".', $phase, $actual));
        }
    }

    /**
     * @Then support has been used
     */
    public function supportHasBeenUsed(): void
    {
        if (!$this->refetchGame()->isSupportUsed()) {
            throw new \RuntimeException('Expected support to be marked as used, but it was not.');
        }
    }

    /**
     * @Then support has not been used
     */
    public function supportHasNotBeenUsed(): void
    {
        if ($this->refetchGame()->isSupportUsed()) {
            throw new \RuntimeException('Expected support to be unused, but it was marked as used.');
        }
    }

    /**
     * Reads the outcome from a roll response shaped { roll_result: { outcome }, game }.
     *
     * @Then the roll outcome is :outcome
     */
    public function theRollOutcomeIs(string $outcome): void
    {
        $data   = $this->getDecodedResponse();
        $actual = $data['roll_result']['outcome'] ?? null;
        if ($actual !== $outcome) {
            throw new \RuntimeException(\sprintf(
                'Expected roll outcome "%s" but got "%s".',
                $outcome,
                \is_string($actual) ? $actual : \var_export($actual, true),
            ));
        }
    }

    /**
     * Reads the applied modifier from a roll response, proving the modifier math
     * (base + background + support, plus any epilogue support bonus).
     *
     * @Then the roll modifier is :value
     */
    public function theRollModifierIs(int $value): void
    {
        $data   = $this->getDecodedResponse();
        $actual = $data['roll_result']['modifier'] ?? null;
        if ($actual !== $value) {
            throw new \RuntimeException(\sprintf('Expected roll modifier %d but got %s.', $value, \var_export($actual, true)));
        }
    }
}
