<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Chess\Ai\StockfishHealthChecker;
use App\Chess\Exception\EngineUnavailableException;
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

        if ('ready' !== $report['status']) {
            throw new EngineUnavailableException('Chess engine is unavailable.');
        }

        return new JsonResponse([
            'engine' => $report['engine'],
            'status' => $report['status'],
            'version' => $report['version'],
        ]);
    }
}
