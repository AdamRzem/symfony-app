<?php

declare(strict_types=1);

namespace App\Chess\Rules;

use App\Chess\Engine\PositionSnapshot;
use App\Chess\Engine\StockfishClient;

final class StockfishRulesEngine implements ChessRulesEngineInterface
{
    public function __construct(private readonly StockfishClient $stockfishClient)
    {
    }

    public function validateMove(string $fen, string $uciMove): MoveValidationResult
    {
        $normalizedMove = strtolower(trim($uciMove));
        $legalMoves = $this->stockfishClient->listLegalMoves($fen);

        if (!preg_match('/^[a-h][1-8][a-h][1-8][qrbn]?$/', $normalizedMove)) {
            return MoveValidationResult::illegal($legalMoves);
        }

        if (!in_array($normalizedMove, $legalMoves, true)) {
            return MoveValidationResult::illegal($legalMoves);
        }

        $positionAfterMove = $this->stockfishClient->inspectPositionAfterMove($fen, $normalizedMove);
        [$status, $isCheck, $isCheckmate, $isStalemate] = $this->deriveStatus($positionAfterMove);

        return MoveValidationResult::legal(
            $status,
            $positionAfterMove->fen,
            $isCheck,
            $isCheckmate,
            $isStalemate,
            $legalMoves,
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
