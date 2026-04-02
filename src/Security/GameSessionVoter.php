<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\GameSession;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, GameSession>
 */
class GameSessionVoter extends Voter
{
    public const string VIEW = 'VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof GameSession;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        // Sessions are public — no ownership concept
        return true;
    }
}
