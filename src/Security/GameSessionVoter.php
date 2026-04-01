<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\GameSession;
use App\Entity\User;
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
        /** @var GameSession $subject */
        $owner = $subject->getOwner();

        // Guest sessions are publicly accessible
        if ($owner === null) {
            return true;
        }

        $currentUser = $token->getUser();

        // No authenticated user, but session has an owner → deny
        if (!$currentUser instanceof User) {
            return false;
        }

        return $owner->getId() === $currentUser->getId();
    }
}
