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
     * Loads a GameSession with all four collections eagerly hydrated (2 queries
     * instead of the default ~6 lazy-load queries triggered by serializeGameState).
     *
     * Doctrine does not allow more than one collection JOIN FETCH per query, so
     * we run two queries and let the identity map merge them into a single entity.
     */
    public function findWithEagerCollections(string $id): ?GameSession
    {
        /** @var GameSession|null $game */
        $game = $this->createQueryBuilder('g')
            ->addSelect('a, b')
            ->leftJoin('g.attributes', 'a')
            ->leftJoin('g.books', 'b')
            ->where('g.id = :id')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();

        if ($game === null) {
            return null;
        }

        // Second query initialises journal_entries and roll_results via the
        // identity map, so the same $game instance is returned fully hydrated.
        $this->createQueryBuilder('g')
            ->addSelect('j, r')
            ->leftJoin('g.journal_entries', 'j')
            ->leftJoin('g.roll_results', 'r')
            ->where('g.id = :id')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getResult();

        return $game;
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
