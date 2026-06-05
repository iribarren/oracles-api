<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\GameSession;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Controls access to GameSession resources.
 *
 * Ownerless sessions (owner = null) are public: any visitor can view them.
 * Owned sessions are private: only the owner may access them. Anyone else —
 * including anonymous users — receives a 403.
 *
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
        $owner = $subject->getOwner();

        if ($owner === null) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $owner->getId() === $user->getId();
    }
}
