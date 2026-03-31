<?php

declare(strict_types=1);

namespace App\Chess\Ai;

final readonly class AiMoveResult
{
    private function __construct(
        public bool $hasMove,
        public ?string $uciMove,
        public ?string $fenAfter,
        public string $status,
        public bool $isCheck,
        public bool $isCheckmate,
        public bool $isStalemate,
    ) {
    }

    public static function noMove(string $status, bool $isCheck, bool $isCheckmate, bool $isStalemate): self
    {
        return new self(false, null, null, $status, $isCheck, $isCheckmate, $isStalemate);
    }

    public static function withMove(
        string $uciMove,
        string $fenAfter,
        string $status,
        bool $isCheck,
        bool $isCheckmate,
        bool $isStalemate,
    ): self {
        return new self(true, $uciMove, $fenAfter, $status, $isCheck, $isCheckmate, $isStalemate);
    }
}
