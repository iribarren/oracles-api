<?php

declare(strict_types=1);

namespace App\Enum;

enum RollOutcome: string
{
    case HIT      = 'hit';
    case WEAK_HIT = 'weak_hit';
    case MISS     = 'miss';
}
