<?php

declare(strict_types=1);

namespace App\Chess\Ai;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final class StockfishHealthChecker
{
    public function __construct(
        private readonly string $binaryPath,
        private readonly int $moveTimeMs,
        private readonly int $skillLevel,
    ) {
    }

    /**
     * @return array{
     *     engine: string,
     *     status: string,
     *     binaryPath: string,
     *     version: ?string,
     *     moveTimeMs: int,
     *     skillLevel: int,
     *     errors: list<string>
     * }
     */
    public function check(): array
    {
        $errors = [];

        if ('' === trim($this->binaryPath)) {
            $errors[] = 'CHESS_STOCKFISH_PATH is empty.';

            return $this->buildReport('unavailable', null, $errors);
        }

        if (!is_file($this->binaryPath)) {
            $errors[] = sprintf('Stockfish binary does not exist: %s', $this->binaryPath);

            return $this->buildReport('unavailable', null, $errors);
        }

        $process = new Process([$this->binaryPath]);
        $process->setInput("uci\nquit\n");
        $process->setTimeout(5);

        try {
            $process->mustRun();
        } catch (ProcessTimedOutException $exception) {
            $errors[] = sprintf('Stockfish process timed out: %s', $exception->getMessage());

            return $this->buildReport('unavailable', null, $errors);
        } catch (ProcessFailedException $exception) {
            $errors[] = sprintf('Stockfish process failed: %s', $exception->getMessage());

            return $this->buildReport('unavailable', null, $errors);
        }

        $output = $process->getOutput();
        $version = $this->extractVersion($output);

        if (null === $version) {
            $errors[] = 'Stockfish output does not contain id name line.';

            return $this->buildReport('unavailable', null, $errors);
        }

        return $this->buildReport('ready', $version, []);
    }

    private function extractVersion(string $output): ?string
    {
        $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (str_starts_with($trimmed, 'id name ')) {
                return trim(substr($trimmed, strlen('id name ')));
            }
        }

        return null;
    }

    /**
     * @param list<string> $errors
     *
     * @return array{
     *     engine: string,
     *     status: string,
     *     binaryPath: string,
     *     version: ?string,
     *     moveTimeMs: int,
     *     skillLevel: int,
     *     errors: list<string>
     * }
     */
    private function buildReport(string $status, ?string $version, array $errors): array
    {
        return [
            'engine' => 'stockfish',
            'status' => $status,
            'binaryPath' => $this->binaryPath,
            'version' => $version,
            'moveTimeMs' => $this->moveTimeMs,
            'skillLevel' => $this->skillLevel,
            'errors' => $errors,
        ];
    }
}
