<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OracleOption;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OracleOption>
 */
class OracleOptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OracleOption::class);
    }
}
