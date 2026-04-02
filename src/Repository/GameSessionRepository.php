<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GameSession;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameSession>
 */
class GameSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameSession::class);
    }

    /**
     * Returns all game sessions ordered by created_at descending (most recent first).
     *
     * @return GameSession[]
     */
    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('g')
            ->orderBy('g.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return GameSession[]
     */
    public function findByOwnerOrderedByDate(User $owner): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('g.updated_at', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
