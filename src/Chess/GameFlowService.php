<?php

declare(strict_types=1);

namespace App\Chess;

use App\Chess\Ai\ChessAiEngineInterface;
use App\Chess\Ai\AiMoveResult;
use App\Chess\Exception\EngineFailureException;
use App\Chess\Exception\InvalidMoveException;
use App\Chess\Rules\ChessRulesEngineInterface;
use App\Chess\Rules\MoveValidationResult;
use App\Entity\Enum\GameResult;
use App\Entity\Enum\GameStatus;
use App\Entity\Enum\PromotionPiece;
use App\Entity\Enum\Side;
use App\Entity\Game;
use App\Entity\Move;

final class GameFlowService
{
    public function __construct(
        private readonly ChessRulesEngineInterface $rulesEngine,
        private readonly ChessAiEngineInterface $aiEngine,
    ) {
    }

    public function applyPlayerMove(Game $game, string $uciMove): Move
    {
        $this->guardGameNotFinished($game);

        $moverSide = $game->getTurn();
        $validation = $this->rulesEngine->validateMove($game->getFen(), $uciMove);

        if (!$validation->isLegal || null === $validation->fenAfter) {
            throw new InvalidMoveException(sprintf('Illegal move: %s', $uciMove));
        }

        return $this->applyValidatedMove($game, strtolower(trim($uciMove)), $validation, $moverSide);
    }

    public function applyAiMove(Game $game): ?Move
    {
        $this->guardGameNotFinished($game);

        if ($game->getTurn() !== $game->getAiColor()) {
            throw new InvalidMoveException('It is not AI turn.');
        }

        $aiMove = $this->aiEngine->generateMove($game->getFen());

        if (!$aiMove->hasMove) {
            $this->applyNoMoveStatus($game, $aiMove);

            return null;
        }

        if (null === $aiMove->uciMove || null === $aiMove->fenAfter) {
            throw new EngineFailureException('AI move result is incomplete.');
        }

        $validation = MoveValidationResult::legal(
            $aiMove->status,
            $aiMove->fenAfter,
            $aiMove->isCheck,
            $aiMove->isCheckmate,
            $aiMove->isStalemate,
            [],
        );

        return $this->applyValidatedMove($game, $aiMove->uciMove, $validation, $game->getTurn());
    }

    private function guardGameNotFinished(Game $game): void
    {
        if ($this->isTerminalStatus($game->getStatus())) {
            throw new InvalidMoveException(sprintf('Game is finished with status %s.', $game->getStatus()->value));
        }
    }

    private function isTerminalStatus(GameStatus $status): bool
    {
        return in_array($status, [
            GameStatus::Checkmate,
            GameStatus::Stalemate,
            GameStatus::Draw,
            GameStatus::Resigned,
        ], true);
    }

    private function applyNoMoveStatus(Game $game, AiMoveResult $aiMove): void
    {
        if ($aiMove->isCheckmate) {
            $game->setStatus(GameStatus::Checkmate);
            $game->setResult(Side::White === $game->getAiColor() ? GameResult::BlackWin : GameResult::WhiteWin);
            $game->touch();

            return;
        }

        if ($aiMove->isStalemate) {
            $game->setStatus(GameStatus::Stalemate);
            $game->setResult(GameResult::Draw);
            $game->touch();

            return;
        }

        $game->setStatus(GameStatus::InProgress);
        $game->setResult(GameResult::Ongoing);
        $game->touch();
    }

    private function applyValidatedMove(Game $game, string $uciMove, MoveValidationResult $validation, Side $moverSide): Move
    {
        $fenAfter = $validation->fenAfter;

        if (null === $fenAfter) {
            throw new EngineFailureException('Validated move is missing resulting FEN.');
        }

        $nextPly = $game->getMoves()->count() + 1;
        $move = new Move(
            $game,
            $nextPly,
            $uciMove,
            $fenAfter,
            $this->extractPromotionPiece($uciMove),
            null,
            $validation->isCheck,
            $validation->isCheckmate,
        );

        $game
            ->setFen($fenAfter)
            ->setTurn($this->extractTurnFromFen($fenAfter));

        $this->applyGameStatusFromValidation($game, $validation, $moverSide);
        $game->touch();

        return $move;
    }

    private function applyGameStatusFromValidation(Game $game, MoveValidationResult $validation, Side $moverSide): void
    {
        if ($validation->isCheckmate) {
            $game->setStatus(GameStatus::Checkmate);
            $game->setResult(Side::White === $moverSide ? GameResult::WhiteWin : GameResult::BlackWin);

            return;
        }

        if ($validation->isStalemate) {
            $game->setStatus(GameStatus::Stalemate);
            $game->setResult(GameResult::Draw);

            return;
        }

        if ($validation->isCheck) {
            $game->setStatus(GameStatus::Check);
            $game->setResult(GameResult::Ongoing);

            return;
        }

        $game->setStatus(GameStatus::InProgress);
        $game->setResult(GameResult::Ongoing);
    }

    private function extractTurnFromFen(string $fen): Side
    {
        $parts = explode(' ', trim($fen));

        if (!isset($parts[1])) {
            throw new EngineFailureException(sprintf('Invalid FEN returned by engine: %s', $fen));
        }

        return match ($parts[1]) {
            'w' => Side::White,
            'b' => Side::Black,
            default => throw new EngineFailureException(sprintf('Invalid side to move in FEN: %s', $fen)),
        };
    }

    private function extractPromotionPiece(string $uciMove): ?PromotionPiece
    {
        if (strlen($uciMove) < 5) {
            return null;
        }

        return match (strtolower($uciMove[4])) {
            'q' => PromotionPiece::Queen,
            'r' => PromotionPiece::Rook,
            'b' => PromotionPiece::Bishop,
            'n' => PromotionPiece::Knight,
            default => null,
        };
    }
}
