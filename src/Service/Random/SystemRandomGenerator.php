<?php

declare(strict_types=1);

namespace App\Service\Random;

/**
 * Production randomness source, backed by PHP's CSPRNG.
 *
 * Autowired as the default RandomGeneratorInterface implementation.
 */
final class SystemRandomGenerator implements RandomGeneratorInterface
{
    public function int(int $min, int $max): int
    {
        return random_int($min, $max);
    }
}
