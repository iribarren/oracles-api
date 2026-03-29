<?php

declare(strict_types=1);

namespace App\Enum;

enum AttributeType: string
{
    case BODY   = 'body';
    case MIND   = 'mind';
    case SOCIAL = 'social';
}
