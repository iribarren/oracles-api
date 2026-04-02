<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use OpenApi\Attributes as OA;
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

    #[OA\Get(
        path: '/api/health',
        operationId: 'healthCheck',
        summary: 'Application health check with database connectivity status',
        tags: ['System'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Service is healthy',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'healthy'),
                    new OA\Property(property: 'checks', properties: [
                        new OA\Property(property: 'database', properties: [
                            new OA\Property(property: 'status', type: 'string', example: 'up'),
                            new OA\Property(property: 'latency_ms', type: 'integer'),
                        ], type: 'object'),
                    ], type: 'object'),
                    new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                ])
            ),
            new OA\Response(
                response: 503,
                description: 'Service is unhealthy (database unreachable)',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'unhealthy'),
                    new OA\Property(property: 'checks', properties: [
                        new OA\Property(property: 'database', properties: [
                            new OA\Property(property: 'status', type: 'string', example: 'down'),
                            new OA\Property(property: 'error', type: 'string'),
                        ], type: 'object'),
                    ], type: 'object'),
                    new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                ])
            ),
        ]
    )]
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
