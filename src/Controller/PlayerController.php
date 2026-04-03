<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\GameSession;
use App\Entity\User;
use App\Repository\GameSessionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/player')]
#[IsGranted('ROLE_PLAYER')]
class PlayerController extends AbstractController
{
    public function __construct(
        private readonly GameSessionRepository $gameSessionRepository,
    ) {}

    #[Route('/sessions', name: 'api_player_sessions', methods: ['GET'])]
    public function sessions(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $sessions = $this->gameSessionRepository->findByOwnerOrderedByDate($user);

        return $this->json(\array_map(static function (GameSession $game): array {
            $phase = $game->getCurrentPhase();
            return [
                'id'             => (string) $game->getId(),
                'character_name' => $game->getCharacterName(),
                'genre'          => $game->getGenre(),
                'epoch'          => $game->getEpoch(),
                'current_phase'  => $phase->value,
                'created_at'     => $game->getCreatedAt()->format('c'),
                'updated_at'     => $game->getUpdatedAt()->format('c'),
                'is_completed'   => $phase->value === 'completed',
            ];
        }, $sessions));
    }
}
