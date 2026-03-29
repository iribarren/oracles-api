<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Enum\RollOutcome;
use App\Service\DiceService;
use PHPUnit\Framework\TestCase;

class DiceServiceTest extends TestCase
{
    private DiceService $diceService;

    protected function setUp(): void
    {
        $this->diceService = new DiceService();
    }

    public function testRollDieSixAlwaysReturnsValueInRange(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $result = $this->diceService->rollDie(6);
            $this->assertGreaterThanOrEqual(1, $result, 'rollDie(6) must be >= 1');
            $this->assertLessThanOrEqual(6, $result, 'rollDie(6) must be <= 6');
        }
    }

    public function testRollDieTenAlwaysReturnsValueInRange(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $result = $this->diceService->rollDie(10);
            $this->assertGreaterThanOrEqual(1, $result, 'rollDie(10) must be >= 1');
            $this->assertLessThanOrEqual(10, $result, 'rollDie(10) must be <= 10');
        }
    }

    public function testRollDieReturnsDifferentValuesOverManyRolls(): void
    {
        $values = [];
        for ($i = 0; $i < 200; $i++) {
            $values[] = $this->diceService->rollDie(6);
        }

        // With 200 rolls of a d6, we should see at least 2 different values
        $this->assertGreaterThan(1, count(array_unique($values)));
    }

    public function testRollActionReturnsRollResultInstance(): void
    {
        $result = $this->diceService->rollAction(0);

        $this->assertInstanceOf(\App\Entity\RollResult::class, $result);
    }

    public function testRollActionSetsAllFields(): void
    {
        $modifier = 2;
        $result   = $this->diceService->rollAction($modifier);

        $this->assertNotNull($result->getActionDie());
        $this->assertNotNull($result->getChallengeDie1());
        $this->assertNotNull($result->getChallengeDie2());
        $this->assertNotNull($result->getModifier());
        $this->assertNotNull($result->getActionScore());
        $this->assertNotNull($result->getOutcome());
    }

    public function testRollActionActionScoreEqualsActionDiePlusModifier(): void
    {
        // Run many times to get statistical confidence
        for ($i = 0; $i < 100; $i++) {
            $modifier = random_int(-3, 5);
            $result   = $this->diceService->rollAction($modifier);

            $this->assertSame(
                $result->getActionDie() + $modifier,
                $result->getActionScore(),
                'action_score must equal action_die + modifier'
            );
        }
    }

    public function testRollActionModifierIsStoredCorrectly(): void
    {
        $modifier = 3;
        $result   = $this->diceService->rollAction($modifier);

        $this->assertSame($modifier, $result->getModifier());
    }

    public function testRollActionActionDieIsWithinD6Range(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $result = $this->diceService->rollAction(0);
            $this->assertGreaterThanOrEqual(1, $result->getActionDie());
            $this->assertLessThanOrEqual(6, $result->getActionDie());
        }
    }

    public function testRollActionChallengeDiceAreWithinD10Range(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $result = $this->diceService->rollAction(0);
            $this->assertGreaterThanOrEqual(1, $result->getChallengeDie1());
            $this->assertLessThanOrEqual(10, $result->getChallengeDie1());
            $this->assertGreaterThanOrEqual(1, $result->getChallengeDie2());
            $this->assertLessThanOrEqual(10, $result->getChallengeDie2());
        }
    }

    public function testRollActionOutcomeIsConsistentWithDiceValues(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $result      = $this->diceService->rollAction(0);
            $actionScore = $result->getActionScore();
            $c1          = $result->getChallengeDie1();
            $c2          = $result->getChallengeDie2();
            $outcome     = $result->getOutcome();

            $beatsFirst  = $actionScore >= $c1;
            $beatsSecond = $actionScore >= $c2;

            if ($beatsFirst && $beatsSecond) {
                $this->assertSame(RollOutcome::HIT, $outcome, 'Beats both dice must be HIT');
            } elseif ($beatsFirst || $beatsSecond) {
                $this->assertSame(RollOutcome::WEAK_HIT, $outcome, 'Beats one die must be WEAK_HIT');
            } else {
                $this->assertSame(RollOutcome::MISS, $outcome, 'Beats no die must be MISS');
            }
        }
    }

    public function testRollActionReturnsOneOfThreeOutcomes(): void
    {
        $validOutcomes = [RollOutcome::HIT, RollOutcome::WEAK_HIT, RollOutcome::MISS];

        for ($i = 0; $i < 100; $i++) {
            $result = $this->diceService->rollAction(0);
            $this->assertContains($result->getOutcome(), $validOutcomes);
        }
    }

    public function testRollActionWithHighModifierOftenHits(): void
    {
        // With modifier=20, action_score = action_die(1-6) + 20 = 21-26
        // Both d10 challenge dice (1-10) will almost always be beaten
        $hitCount = 0;
        for ($i = 0; $i < 100; $i++) {
            $result = $this->diceService->rollAction(20);
            if ($result->getOutcome() === RollOutcome::HIT) {
                $hitCount++;
            }
        }
        // Should be HIT in nearly all cases
        $this->assertGreaterThan(90, $hitCount, 'High modifier should produce mostly HITs');
    }

    public function testRollActionWithVeryNegativeModifierOftenMisses(): void
    {
        // With modifier=-20, action_score = action_die(1-6) - 20 = -19 to -14
        // Both d10 challenge dice (1-10) will almost always beat it
        $missCount = 0;
        for ($i = 0; $i < 100; $i++) {
            $result = $this->diceService->rollAction(-20);
            if ($result->getOutcome() === RollOutcome::MISS) {
                $missCount++;
            }
        }
        $this->assertGreaterThan(90, $missCount, 'Very low modifier should produce mostly MISSes');
    }
}
