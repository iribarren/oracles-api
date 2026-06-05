<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\GameSession;
use App\Entity\User;
use App\Repository\GameSessionRepository;
use OpenApi\Attributes as OA;
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

    #[OA\Get(
        path: '/api/player/sessions',
        operationId: 'playerSessions',
        summary: 'List the authenticated player\'s game sessions',
        tags: ['Player'],
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Array of game session summaries ordered by most recent',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/GameSummary'))
            ),
            new OA\Response(response: 401, description: 'Unauthorized — JWT token missing or expired'),
            new OA\Response(response: 403, description: 'Forbidden — ROLE_PLAYER required'),
        ]
    )]
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
