<?php

declare(strict_types=1);

namespace App\Service\Random;

/**
 * Single source of randomness for the dice engine.
 *
 * Extracting entropy behind this interface lets the test environment swap in a
 * deterministic implementation (see App\Tests\Behat\Dice\QueuedRandomGenerator)
 * while production keeps using the system CSPRNG. The dice resolution logic in
 * DiceService stays untouched and is exercised with controlled values.
 */
interface RandomGeneratorInterface
{
    /**
     * Returns an integer uniformly distributed in [$min, $max].
     */
    public function int(int $min, int $max): int;
}
