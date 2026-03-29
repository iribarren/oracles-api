<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    // -------------------------------------------------------------------------
    // GET /api/health — Application health check
    // -------------------------------------------------------------------------

    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $timestamp = (new \DateTimeImmutable())->format('c');

        $start = \hrtime(true);
        try {
            $this->connection->executeQuery('SELECT 1');
            $latencyMs = (int) \round((\hrtime(true) - $start) / 1_000_000);

            return $this->json([
                'status' => 'healthy',
                'checks' => [
                    'database' => [
                        'status'     => 'up',
                        'latency_ms' => $latencyMs,
                    ],
                ],
                'timestamp' => $timestamp,
            ]);
        } catch (DBALException $e) {
            return $this->json([
                'status' => 'unhealthy',
                'checks' => [
                    'database' => [
                        'status' => 'down',
                        'error'  => $e->getMessage(),
                    ],
                ],
                'timestamp' => $timestamp,
            ], 503);
        }
    }
}
