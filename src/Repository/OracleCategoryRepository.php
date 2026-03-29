<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OracleCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OracleCategory>
 */
class OracleCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OracleCategory::class);
    }

    /**
     * Returns all categories with their active options, ordered by display_order.
     *
     * @return OracleCategory[]
     */
    public function findAllWithOptions(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.options', 'o', 'WITH', 'o.is_active = true')
            ->addSelect('o')
            ->orderBy('c.display_order', 'ASC')
            ->addOrderBy('o.display_order', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns all categories with ALL options (including inactive), for admin use.
     *
     * @return OracleCategory[]
     */
    public function findAllWithAllOptions(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.options', 'o')
            ->addSelect('o')
            ->orderBy('c.display_order', 'ASC')
            ->addOrderBy('o.display_order', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
