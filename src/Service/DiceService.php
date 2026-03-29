<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RollResult;
use App\Enum\RollOutcome;

class DiceService
{
    /**
     * Rolls a die with the given number of sides.
     * Returns an integer in [1, $sides].
     */
    public function rollDie(int $sides): int
    {
        return random_int(1, $sides);
    }

    /**
     * Executes the core Iron-sworn dice mechanic:
     *   - Rolls 1d6 as action_die
     *   - Rolls 2d10 independently as challenge_die_1 and challenge_die_2
     *   - action_score = action_die + modifier
     *   - HIT      if action_score >= both challenge dice
     *   - WEAK_HIT if action_score >= exactly one challenge die
     *   - MISS     if action_score < both challenge dice
     *
     * Returns a new (un-persisted) RollResult entity.
     */
    public function rollAction(int $modifier): RollResult
    {
        $actionDie     = $this->rollDie(6);
        $challengeDie1 = $this->rollDie(10);
        $challengeDie2 = $this->rollDie(10);
        $actionScore   = $actionDie + $modifier;

        $beatsFirst  = $actionScore >= $challengeDie1;
        $beatsSecond = $actionScore >= $challengeDie2;

        $outcome = match (true) {
            $beatsFirst && $beatsSecond   => RollOutcome::HIT,
            $beatsFirst || $beatsSecond   => RollOutcome::WEAK_HIT,
            default                        => RollOutcome::MISS,
        };

        $rollResult = new RollResult();
        $rollResult->setActionDie($actionDie);
        $rollResult->setChallengeDie1($challengeDie1);
        $rollResult->setChallengeDie2($challengeDie2);
        $rollResult->setModifier($modifier);
        $rollResult->setActionScore($actionScore);
        $rollResult->setOutcome($outcome);

        return $rollResult;
    }
}
