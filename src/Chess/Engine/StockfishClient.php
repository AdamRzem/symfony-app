<?php

declare(strict_types=1);

namespace App\Chess\Engine;

use App\Chess\Exception\EngineFailureException;
use App\Chess\Exception\EngineUnavailableException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class StockfishClient
{
    public function __construct(
        private readonly string $binaryPath,
        private readonly int $defaultMoveTimeMs,
        private readonly int $skillLevel,
    ) {
    }

    /**
     * @return list<string>
     */
    public function listLegalMoves(string $fen): array
    {
        $output = $this->runSession([
            sprintf('position fen %s', $this->normalizeFen($fen)),
            'go perft 1',
        ]);

        return $this->extractLegalMoves($output);
    }

    public function getFenAfterMove(string $fen, string $uciMove): string
    {
        $output = $this->runSession([
            sprintf('position fen %s moves %s', $this->normalizeFen($fen), strtolower(trim($uciMove))),
            'd',
        ]);

        $resultFen = $this->extractFen($output);

        if (null === $resultFen) {
            throw new EngineFailureException('Unable to extract resulting FEN from Stockfish output.');
        }

        return $resultFen;
    }

    public function inspectPosition(string $fen): PositionSnapshot
    {
        $output = $this->runSession([
            sprintf('position fen %s', $this->normalizeFen($fen)),
            'd',
            'go perft 1',
        ]);

        $positionFen = $this->extractFen($output);

        if (null === $positionFen) {
            throw new EngineFailureException('Unable to extract FEN from Stockfish position inspection output.');
        }

        return new PositionSnapshot(
            $positionFen,
            $this->extractCheckers($output),
            $this->extractLegalMoves($output),
        );
    }

    public function findBestMove(string $fen, ?int $moveTimeMs = null): ?string
    {
        $thinkTime = $moveTimeMs ?? $this->defaultMoveTimeMs;

        $output = $this->runSession([
            sprintf('position fen %s', $this->normalizeFen($fen)),
            sprintf('go movetime %d', max(1, $thinkTime)),
        ]);

        if (!preg_match('/^bestmove\s+(\S+)/mi', $output, $matches)) {
            throw new EngineFailureException('Unable to extract bestmove from Stockfish output.');
        }

        $bestMove = strtolower(trim($matches[1]));

        if ('(none)' === $bestMove || '0000' === $bestMove) {
            return null;
        }

        return $bestMove;
    }

    /**
     * @param list<string> $commands
     */
    private function runSession(array $commands): string
    {
        if ('' === trim($this->binaryPath)) {
            throw new EngineUnavailableException('CHESS_STOCKFISH_PATH is empty.');
        }

        if (!is_file($this->binaryPath)) {
            throw new EngineUnavailableException(sprintf('Stockfish binary does not exist: %s', $this->binaryPath));
        }

        $sessionCommands = [
            'uci',
            sprintf('setoption name Skill Level value %d', max(0, min(20, $this->skillLevel))),
            'isready',
            ...$commands,
            'quit',
        ];

        $process = new Process([$this->binaryPath]);
        $process->setInput(implode("\n", $sessionCommands)."\n");
        $process->setTimeout(12);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            throw new EngineUnavailableException(
                sprintf('Stockfish process failed: %s', $exception->getMessage()),
                0,
                $exception,
            );
        }

        return $process->getOutput()."\n".$process->getErrorOutput();
    }

    private function normalizeFen(string $fen): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($fen)) ?? '';

        if ('' === $normalized) {
            throw new EngineFailureException('FEN cannot be empty.');
        }

        return $normalized;
    }

    private function extractFen(string $output): ?string
    {
        if (!preg_match('/^Fen:\s+(.+)$/mi', $output, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    /**
     * @return list<string>
     */
    private function extractCheckers(string $output): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if (!str_starts_with($trimmedLine, 'Checkers:')) {
                continue;
            }

            $raw = trim(substr($trimmedLine, strlen('Checkers:')));

            if ('' === $raw) {
                return [];
            }

            $tokens = preg_split('/\s+/', $raw) ?: [];

            return array_values(array_filter(array_map(static fn (string $checker): string => strtolower(trim($checker)), $tokens)));
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function extractLegalMoves(string $output): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];
        $legalMoves = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (preg_match('/^([a-h][1-8][a-h][1-8][qrbn]?):\s+\d+$/', $trimmed, $matches)) {
                $legalMoves[] = strtolower($matches[1]);
            }
        }

        return array_values(array_unique($legalMoves));
    }
}
