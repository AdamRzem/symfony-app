<?php

declare(strict_types=1);

namespace App\Chess\Engine;

final readonly class PositionSnapshot
{
    /**
     * @param list<string> $checkers
     * @param list<string> $legalMoves
     */
    public function __construct(
        public string $fen,
        public array $checkers,
        public array $legalMoves,
    ) {
    }

    public function isInCheck(): bool
    {
        return [] !== $this->checkers;
    }
}
