<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\ChessResponseMapper;
use App\Api\Exception\BadPayloadException;
use App\Chess\GameApplicationService;
use App\Dto\Request\CreateGameRequest;
use App\Dto\Request\MakeMoveRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/games', name: 'api_v1_games_')]
final class GameController
{
    public function __construct(
        private readonly GameApplicationService $gameApplicationService,
        private readonly ChessResponseMapper $responseMapper,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->decodeJsonBody($request, true);
        $dto = new CreateGameRequest($payload['aiColor'] ?? null);
        $this->validateOrFail($dto);

        $game = $this->gameApplicationService->createGame($dto->aiColor);

        return new JsonResponse($this->responseMapper->game($game), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{gameId}', name: 'get', methods: ['GET'])]
    public function getGame(string $gameId): JsonResponse
    {
        $game = $this->gameApplicationService->getGame($gameId);

        return new JsonResponse($this->responseMapper->game($game));
    }

    #[Route('/{gameId}/moves', name: 'move', methods: ['POST'])]
    public function makeMove(string $gameId, Request $request): JsonResponse
    {
        $payload = $this->decodeJsonBody($request, false);
        $dto = new MakeMoveRequest((string) ($payload['uciMove'] ?? ''));
        $this->validateOrFail($dto);

        $game = $this->gameApplicationService->makeMove($gameId, $dto->uciMove);

        return new JsonResponse($this->responseMapper->game($game));
    }

    #[Route('/{gameId}/moves', name: 'moves_list', methods: ['GET'])]
    public function listMoves(string $gameId): JsonResponse
    {
        $moves = $this->gameApplicationService->listMoves($gameId);

        return new JsonResponse($this->responseMapper->moveList($moves));
    }

    #[Route('/{gameId}/ai-move', name: 'ai_move', methods: ['POST'])]
    public function makeAiMove(string $gameId): JsonResponse
    {
        $game = $this->gameApplicationService->makeAiMove($gameId);

        return new JsonResponse($this->responseMapper->game($game));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonBody(Request $request, bool $allowEmptyBody): array
    {
        $content = trim($request->getContent());

        if ('' === $content) {
            if ($allowEmptyBody) {
                return [];
            }

            throw new BadPayloadException('Request JSON body is required.');
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new BadPayloadException('Invalid JSON payload.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new BadPayloadException('JSON payload must be an object.');
        }

        return $decoded;
    }

    private function validateOrFail(object $dto): void
    {
        $violations = $this->validator->validate($dto);

        if (0 !== $violations->count()) {
            throw new ValidationFailedException($dto, $violations);
        }
    }
}
