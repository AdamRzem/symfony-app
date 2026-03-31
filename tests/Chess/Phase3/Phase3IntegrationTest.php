<?php

declare(strict_types=1);

namespace App\Tests\Chess\Phase3;

use App\Chess\Ai\ChessAiEngineInterface;
use App\Chess\Ai\StockfishAiEngine;
use App\Chess\Ai\StockfishHealthChecker;
use App\Chess\Engine\StockfishClient;
use App\Chess\GameFlowService;
use App\Chess\Rules\ChessRulesEngineInterface;
use App\Chess\Rules\StockfishRulesEngine;
use App\Entity\Enum\Side;
use App\Entity\Game;
use PHPUnit\Framework\TestCase;

final class Phase3IntegrationTest extends TestCase
{
    private StockfishHealthChecker $healthChecker;
    private ChessRulesEngineInterface $rulesEngine;
    private ChessAiEngineInterface $aiEngine;
    private GameFlowService $gameFlowService;

    protected function setUp(): void
    {
        $binaryPath = (string) ($_ENV['CHESS_STOCKFISH_PATH'] ?? '');
        $moveTimeMs = (int) ($_ENV['CHESS_STOCKFISH_MOVE_TIME_MS'] ?? 120);
        $skillLevel = (int) ($_ENV['CHESS_STOCKFISH_SKILL'] ?? 6);

        $this->healthChecker = new StockfishHealthChecker($binaryPath, $moveTimeMs, $skillLevel);

        $stockfishClient = new StockfishClient($binaryPath, $moveTimeMs, $skillLevel);
        $this->rulesEngine = new StockfishRulesEngine($stockfishClient);
        $this->aiEngine = new StockfishAiEngine($stockfishClient, $this->rulesEngine);
        $this->gameFlowService = new GameFlowService($this->rulesEngine, $this->aiEngine);

        $health = $this->healthChecker->check();

        if ('ready' !== $health['status']) {
            self::markTestSkipped('Stockfish is not ready for integration tests.');
        }
    }

    public function testRulesEngineValidatesLegalAndIllegalMoves(): void
    {
        $legalResult = $this->rulesEngine->validateMove(Game::INITIAL_FEN, 'e2e4');
        self::assertTrue($legalResult->isLegal);
        self::assertNotNull($legalResult->fenAfter);
        self::assertSame('in_progress', $legalResult->status);

        $illegalResult = $this->rulesEngine->validateMove(Game::INITIAL_FEN, 'e2e5');
        self::assertFalse($illegalResult->isLegal);
        self::assertNull($illegalResult->fenAfter);
    }

    public function testAiEngineGeneratesMoveFromStartPosition(): void
    {
        $result = $this->aiEngine->generateMove(Game::INITIAL_FEN);

        self::assertTrue($result->hasMove);
        self::assertNotNull($result->uciMove);
        self::assertMatchesRegularExpression('/^[a-h][1-8][a-h][1-8][qrbn]?$/', $result->uciMove);
        self::assertNotNull($result->fenAfter);
    }

    public function testGameFlowAppliesPlayerAndAiMoves(): void
    {
        $game = new Game(Side::Black);

        $playerMove = $this->gameFlowService->applyPlayerMove($game, 'e2e4');
        self::assertSame(1, $playerMove->getPly());
        self::assertSame(Side::Black, $game->getTurn());

        $aiMove = $this->gameFlowService->applyAiMove($game);
        self::assertNotNull($aiMove);
        self::assertSame(2, $aiMove->getPly());
        self::assertSame(Side::White, $game->getTurn());
    }
}
