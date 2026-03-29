<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Chess\Ai\StockfishHealthChecker;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class EngineHealthController
{
    public function __construct(private readonly StockfishHealthChecker $checker)
    {
    }

    #[Route('/api/v1/engine/health', name: 'api_v1_engine_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $report = $this->checker->check();
        $statusCode = 'ready' === $report['status'] ? JsonResponse::HTTP_OK : JsonResponse::HTTP_SERVICE_UNAVAILABLE;

        return new JsonResponse($report, $statusCode);
    }
}
