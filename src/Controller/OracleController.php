<?php

declare(strict_types=1);

namespace App\Controller;

use App\Oracle\OracleService;
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
