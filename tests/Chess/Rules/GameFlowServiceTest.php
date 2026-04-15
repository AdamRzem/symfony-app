<?php

declare(strict_types=1);

namespace App\Tests\Chess\Rules;

use App\Chess\Ai\AiMoveResult;
use App\Chess\Ai\ChessAiEngineInterface;
use App\Chess\Exception\EngineFailureException;
use App\Chess\Exception\InvalidMoveException;
use App\Chess\GameFlowService;
use App\Chess\Rules\ChessRulesEngineInterface;
use App\Chess\Rules\MoveValidationResult;
use App\Entity\Enum\GameResult;
use App\Entity\Enum\GameStatus;
use App\Entity\Enum\PromotionPiece;
use App\Entity\Enum\Side;
use App\Entity\Game;
use PHPUnit\Framework\TestCase;

final class GameFlowServiceTest extends TestCase
{
    public function testApplyPlayerMoveUpdatesGameStateAndCreatesMove(): void
    {
        $fenAfterMove = 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq - 0 1';

        $service = new GameFlowService(
            $this->rulesEngineReturning(MoveValidationResult::legal('in_progress', $fenAfterMove, false, false, false, ['e2e4'])),
            $this->aiEngineReturning(AiMoveResult::noMove('in_progress', false, false, false)),
        );

        $game = new Game(Side::Black);
        $move = $service->applyPlayerMove($game, 'E2E4');

        self::assertSame(1, $move->getPly());
        self::assertSame('e2e4', $move->getUci());
        self::assertNull($move->getPromotion());

        self::assertSame($fenAfterMove, $game->getFen());
        self::assertSame(Side::Black, $game->getTurn());
        self::assertSame(GameStatus::InProgress, $game->getStatus());
        self::assertSame(GameResult::Ongoing, $game->getResult());
    }

    public function testApplyPlayerMoveStoresPromotionPiece(): void
    {
        $fenAfterMove = 'Q7/8/8/8/8/8/8/k6K b - - 0 1';

        $service = new GameFlowService(
            $this->rulesEngineReturning(MoveValidationResult::legal('in_progress', $fenAfterMove, false, false, false, ['a7a8q'])),
            $this->aiEngineReturning(AiMoveResult::noMove('in_progress', false, false, false)),
        );

        $game = new Game(Side::Black, '8/P7/8/8/8/8/8/k6K w - - 0 1');
        $move = $service->applyPlayerMove($game, 'a7a8q');

        self::assertSame(PromotionPiece::Queen, $move->getPromotion());
    }

    public function testApplyPlayerMoveSetsCheckmateAndWinner(): void
    {
        $fenAfterMove = '7k/6Q1/6K1/8/8/8/8/8 b - - 0 1';

        $service = new GameFlowService(
            $this->rulesEngineReturning(MoveValidationResult::legal('checkmate', $fenAfterMove, true, true, false, ['g7g8q'])),
            $this->aiEngineReturning(AiMoveResult::noMove('in_progress', false, false, false)),
        );

        $game = new Game(Side::Black, '7k/6P1/6K1/8/8/8/8/8 w - - 0 1');
        $service->applyPlayerMove($game, 'g7g8q');

        self::assertSame(GameStatus::Checkmate, $game->getStatus());
        self::assertSame(GameResult::WhiteWin, $game->getResult());
    }

    public function testApplyPlayerMoveRejectsIllegalMove(): void
    {
        $service = new GameFlowService(
            $this->rulesEngineReturning(MoveValidationResult::illegal(['e2e4'])),
            $this->aiEngineReturning(AiMoveResult::noMove('in_progress', false, false, false)),
        );

        $game = new Game(Side::Black);

        $this->expectException(InvalidMoveException::class);
        $this->expectExceptionMessage('Illegal move: e2e5');

        $service->applyPlayerMove($game, 'e2e5');
    }

    public function testApplyPlayerMoveRejectsWhenItIsNotPlayerTurn(): void
    {
        $service = new GameFlowService(
            $this->rulesEngineReturning(MoveValidationResult::illegal([])),
            $this->aiEngineReturning(AiMoveResult::noMove('in_progress', false, false, false)),
        );

        $game = new Game(Side::White);

        $this->expectException(InvalidMoveException::class);
        $this->expectExceptionMessage('It is not player turn.');

        $service->applyPlayerMove($game, 'e2e4');
    }

    public function testApplyPlayerMoveFailsWhenResultingFenHasInvalidSideToMove(): void
    {
        $service = new GameFlowService(
            $this->rulesEngineReturning(MoveValidationResult::legal('in_progress', 'invalid-fen', false, false, false, ['e2e4'])),
            $this->aiEngineReturning(AiMoveResult::noMove('in_progress', false, false, false)),
        );

        $game = new Game(Side::Black);

        $this->expectException(EngineFailureException::class);
        $this->expectExceptionMessage('Invalid FEN returned by engine');

        $service->applyPlayerMove($game, 'e2e4');
    }

    public function testApplyAiMoveCreatesMoveWhenAiTurn(): void
    {
        $fenAfterMove = 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq - 0 1';

        $service = new GameFlowService(
            $this->rulesEngineReturning(MoveValidationResult::illegal([])),
            $this->aiEngineReturning(AiMoveResult::withMove('e2e4', $fenAfterMove, 'in_progress', false, false, false)),
        );

        $game = new Game(Side::White);
        $move = $service->applyAiMove($game);

        self::assertNotNull($move);
        self::assertSame(1, $move->getPly());
        self::assertSame('e2e4', $move->getUci());
        self::assertSame(Side::Black, $game->getTurn());
        self::assertSame(GameStatus::InProgress, $game->getStatus());
    }

    public function testApplyAiMoveSetsCheckmateWhenAiHasNoLegalMove(): void
    {
        $service = new GameFlowService(
            $this->rulesEngineReturning(MoveValidationResult::illegal([])),
            $this->aiEngineReturning(AiMoveResult::noMove('checkmate', true, true, false)),
        );

        $game = new Game(Side::White);
        $move = $service->applyAiMove($game);

        self::assertNull($move);
        self::assertSame(GameStatus::Checkmate, $game->getStatus());
        self::assertSame(GameResult::BlackWin, $game->getResult());
    }

    public function testApplyAiMoveRejectsWhenItIsNotAiTurn(): void
    {
        $service = new GameFlowService(
            $this->rulesEngineReturning(MoveValidationResult::illegal([])),
            $this->aiEngineReturning(AiMoveResult::noMove('in_progress', false, false, false)),
        );

        $game = new Game(Side::Black);

        $this->expectException(InvalidMoveException::class);
        $this->expectExceptionMessage('It is not AI turn.');

        $service->applyAiMove($game);
    }

    private function rulesEngineReturning(MoveValidationResult $result): ChessRulesEngineInterface
    {
        return new class($result) implements ChessRulesEngineInterface {
            public function __construct(private readonly MoveValidationResult $result)
            {
            }

            public function validateMove(string $fen, string $uciMove): MoveValidationResult
            {
                return $this->result;
            }
        };
    }

    private function aiEngineReturning(AiMoveResult $result): ChessAiEngineInterface
    {
        return new class($result) implements ChessAiEngineInterface {
            public function __construct(private readonly AiMoveResult $result)
            {
            }

            public function generateMove(string $fen): AiMoveResult
            {
                return $this->result;
            }
        };
    }
}
