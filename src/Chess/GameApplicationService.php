<?php

declare(strict_types=1);

namespace App\Chess;

use App\Chess\Exception\GameNotFoundException;
use App\Entity\Enum\Side;
use App\Entity\Game;
use App\Entity\Move;
use App\Repository\GameRepository;
use App\Repository\MoveRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class GameApplicationService
{
    public function __construct(
        private readonly GameRepository $gameRepository,
        private readonly MoveRepository $moveRepository,
        private readonly GameFlowService $gameFlowService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function createGame(?string $aiColor): Game
    {
        $resolvedAiColor = match ($aiColor) {
            'white' => Side::White,
            default => Side::Black,
        };

        $game = new Game($resolvedAiColor);
        $this->gameRepository->save($game, true);

        return $game;
    }

    public function getGame(string $gameId): Game
    {
        $game = $this->gameRepository->find($gameId);

        if (!$game instanceof Game) {
            throw new GameNotFoundException(sprintf('Game %s not found.', $gameId));
        }

        return $game;
    }

    /**
     * @return list<Move>
     */
    public function listMoves(string $gameId): array
    {
        $this->getGame($gameId);

        return $this->moveRepository->findByGameIdOrderedByPly($gameId);
    }

    public function makeMove(string $gameId, string $uciMove): Game
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $game = $this->getGame($gameId);
            $this->entityManager->lock($game, LockMode::PESSIMISTIC_WRITE);

            $this->gameFlowService->applyPlayerMove($game, $uciMove);
            $this->entityManager->flush();
            $connection->commit();

            return $game;
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public function makeAiMove(string $gameId): Game
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $game = $this->getGame($gameId);
            $this->entityManager->lock($game, LockMode::PESSIMISTIC_WRITE);

            $this->gameFlowService->applyAiMove($game);
            $this->entityManager->flush();
            $connection->commit();

            return $game;
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }
}
