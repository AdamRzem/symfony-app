<?php

declare(strict_types=1);

namespace App\Command;

use App\Chess\Ai\StockfishHealthChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:chess:engine:health',
    description: 'Checks local Stockfish engine readiness for chess AI.',
)]
final class ChessEngineHealthCommand extends Command
{
    public function __construct(private readonly StockfishHealthChecker $checker)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $report = $this->checker->check();

        $output->writeln(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 'ready' === $report['status'] ? Command::SUCCESS : Command::FAILURE;
    }
}
