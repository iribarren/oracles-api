<?php

declare(strict_types=1);

namespace App\Tests\Behat\Dice;

use App\Service\Random\RandomGeneratorInterface;
use RuntimeException;

/**
 * Deterministic randomness source for BDD scenarios.
 *
 * Replaces SystemRandomGenerator in the test environment (see config/services_test.yaml).
 * Scenarios push the exact die faces they want via the Gherkin dice steps; the
 * DiceService then consumes them in order, so every roll outcome is fully
 * controlled and the game rules can be asserted exactly.
 *
 * The kernel reboot is disabled in the FeatureContext, so this is a single shared
 * instance for the whole scenario: the same queue feeds DiceService and the steps.
 */
final class QueuedRandomGenerator implements RandomGeneratorInterface
{
    /** @var list<int> FIFO queue of predetermined die faces. */
    private array $queue = [];

    /**
     * When true, an empty queue throws instead of falling back to random_int().
     *
     * Behat enables strict mode in @BeforeScenario so scenarios fail fast if a
     * dice step is missing. PHPUnit integration tests never enable it, so they
     * behave as if the system random generator were in use.
     */
    private bool $strict = false;

    public function int(int $min, int $max): int
    {
        if ($this->queue === []) {
            if ($this->strict) {
                throw new RuntimeException(
                    'Dice queue is empty: the scenario did not queue enough die faces. '
                    . 'Add a "Given the next ... roll ..." step before the action that rolls.'
                );
            }

            return random_int($min, $max);
        }

        $value = array_shift($this->queue);

        if ($value < $min || $value > $max) {
            throw new RuntimeException(\sprintf(
                'Queued die face %d is out of range [%d, %d]. Check the dice queued by the scenario.',
                $value,
                $min,
                $max,
            ));
        }

        return $value;
    }

    /**
     * Appends die faces to the queue, consumed in order by subsequent rolls.
     */
    public function push(int ...$values): void
    {
        foreach ($values as $value) {
            $this->queue[] = $value;
        }
    }

    /**
     * Enables strict mode: an empty queue throws instead of falling back to random_int().
     * Called by FeatureContext in @BeforeScenario so Behat scenarios fail fast on missing dice steps.
     */
    public function enableStrictMode(): void
    {
        $this->strict = true;
    }

    /**
     * Clears any leftover faces and resets strict mode. Called once per scenario.
     */
    public function reset(): void
    {
        $this->queue  = [];
        $this->strict = false;
    }
}
