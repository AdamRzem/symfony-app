<?php

declare(strict_types=1);

namespace App\Command;

use App\Chess\GameFlowService;
use App\Chess\Ai\StockfishHealthChecker;
use App\Entity\Enum\Side;
use App\Entity\Game;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:chess:phase3:smoke-test',
    description: 'Runs a simple Phase 3 move flow check: player move + AI move.',
)]
final class ChessPhase3SmokeTestCommand extends Command
{
    public function __construct(
        private readonly StockfishHealthChecker $healthChecker,
        private readonly GameFlowService $gameFlowService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $health = $this->healthChecker->check();

        if ('ready' !== $health['status']) {
            $output->writeln(json_encode([
                'ok' => false,
                'reason' => 'engine_unavailable',
                'health' => $health,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return Command::FAILURE;
        }

        $game = new Game(Side::Black);
        $playerMove = $this->gameFlowService->applyPlayerMove($game, 'e2e4');
        $aiMove = $this->gameFlowService->applyAiMove($game);

        $output->writeln(json_encode([
            'ok' => true,
            'gameId' => $game->getId(),
            'playerMove' => $playerMove->getUci(),
            'aiMove' => $aiMove?->getUci(),
            'status' => $game->getStatus()->value,
            'result' => $game->getResult()->value,
            'turn' => $game->getTurn()->value,
            'fen' => $game->getFen(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return Command::SUCCESS;
    }
}
