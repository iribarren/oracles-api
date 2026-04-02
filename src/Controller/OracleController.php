<?php

declare(strict_types=1);

namespace App\Controller;

use App\Oracle\OracleService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/oracle')]
class OracleController extends AbstractController
{
    public function __construct(
        private readonly OracleService $oracleService,
    ) {}

    // -------------------------------------------------------------------------
    // GET /api/oracle/tables — Return all oracle tables
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/oracle/tables',
        operationId: 'getOracleTables',
        summary: 'Return all oracle lookup tables',
        description: 'Returns tables for genre, epoch, color, binding, smell, and interior. Each entry has a value and a hint.',
        tags: ['Oracle'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Oracle tables keyed by category name',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'genre', type: 'array', items: new OA\Items(properties: [new OA\Property(property: 'value', type: 'string'), new OA\Property(property: 'hint', type: 'string')], type: 'object')),
                        new OA\Property(property: 'epoch', type: 'array', items: new OA\Items(properties: [new OA\Property(property: 'value', type: 'string'), new OA\Property(property: 'hint', type: 'string')], type: 'object')),
                        new OA\Property(property: 'color', type: 'array', items: new OA\Items(properties: [new OA\Property(property: 'value', type: 'string'), new OA\Property(property: 'hint', type: 'string')], type: 'object')),
                        new OA\Property(property: 'binding', type: 'array', items: new OA\Items(properties: [new OA\Property(property: 'value', type: 'string'), new OA\Property(property: 'hint', type: 'string')], type: 'object')),
                        new OA\Property(property: 'smell', type: 'array', items: new OA\Items(properties: [new OA\Property(property: 'value', type: 'string'), new OA\Property(property: 'hint', type: 'string')], type: 'object')),
                        new OA\Property(property: 'interior', type: 'array', items: new OA\Items(properties: [new OA\Property(property: 'value', type: 'string'), new OA\Property(property: 'hint', type: 'string')], type: 'object')),
                    ]
                )
            ),
        ]
    )]
    #[Route('/tables', name: 'api_oracle_tables', methods: ['GET'])]
    public function tables(): JsonResponse
    {
        return $this->json([
            'genre'   => $this->oracleService->getGenreTable(),
            'epoch'   => $this->oracleService->getEpochTable(),
            'color'   => $this->oracleService->getColorTable(),
            'binding' => $this->oracleService->getBindingTable(),
            'smell'   => $this->oracleService->getSmellTable(),
            'interior' => $this->oracleService->getInteriorTable(),
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/oracle/random-setting — Return random genre + epoch
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/oracle/random-setting',
        operationId: 'getRandomSetting',
        summary: 'Return a randomly rolled genre and epoch pair',
        tags: ['Oracle'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Random genre and epoch',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'genre', properties: [new OA\Property(property: 'value', type: 'string'), new OA\Property(property: 'hint', type: 'string')], type: 'object'),
                        new OA\Property(property: 'epoch', properties: [new OA\Property(property: 'value', type: 'string'), new OA\Property(property: 'hint', type: 'string')], type: 'object'),
                    ]
                )
            ),
        ]
    )]
    #[Route('/random-setting', name: 'api_oracle_random_setting', methods: ['GET'])]
    public function randomSetting(): JsonResponse
    {
        $genre = $this->oracleService->getRandomFromTable($this->oracleService->getGenreTable());
        $epoch = $this->oracleService->getRandomFromTable($this->oracleService->getEpochTable());

        return $this->json([
            'genre' => $genre,
            'epoch' => $epoch,
        ]);
    }
}
