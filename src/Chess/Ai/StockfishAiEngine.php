<?php

declare(strict_types=1);

namespace App\Chess\Ai;

use App\Chess\Engine\PositionSnapshot;
use App\Chess\Engine\StockfishClient;
use App\Chess\Exception\EngineFailureException;
use App\Chess\Rules\ChessRulesEngineInterface;

final class StockfishAiEngine implements ChessAiEngineInterface
{
    public function __construct(
        private readonly StockfishClient $stockfishClient,
        private readonly ChessRulesEngineInterface $rulesEngine,
    ) {
    }

    public function generateMove(string $fen): AiMoveResult
    {
        $snapshot = $this->stockfishClient->inspectPosition($fen);
        [$status, $isCheck, $isCheckmate, $isStalemate] = $this->deriveStatus($snapshot);

        if ([] === $snapshot->legalMoves) {
            return AiMoveResult::noMove($status, $isCheck, $isCheckmate, $isStalemate);
        }

        $bestMove = $this->stockfishClient->findBestMove($fen);

        if (null === $bestMove || !in_array($bestMove, $snapshot->legalMoves, true)) {
            $bestMove = $snapshot->legalMoves[0];
        }

        $validation = $this->rulesEngine->validateMove($fen, $bestMove);

        if (!$validation->isLegal || null === $validation->fenAfter) {
            throw new EngineFailureException('Stockfish generated an invalid move.');
        }

        return AiMoveResult::withMove(
            $bestMove,
            $validation->fenAfter,
            $validation->status,
            $validation->isCheck,
            $validation->isCheckmate,
            $validation->isStalemate,
        );
    }

    /**
     * @return array{0: string, 1: bool, 2: bool, 3: bool}
     */
    private function deriveStatus(PositionSnapshot $snapshot): array
    {
        $inCheck = $snapshot->isInCheck();

        if ([] === $snapshot->legalMoves) {
            if ($inCheck) {
                return ['checkmate', true, true, false];
            }

            return ['stalemate', false, false, true];
        }

        if ($inCheck) {
            return ['check', true, false, false];
        }

        return ['in_progress', false, false, false];
    }
}
