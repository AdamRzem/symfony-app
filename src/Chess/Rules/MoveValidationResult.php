<?php

declare(strict_types=1);

namespace App\Chess\Rules;

final readonly class MoveValidationResult
{
    /**
     * @param list<string> $legalMoves
     */
    private function __construct(
        public bool $isLegal,
        public string $status,
        public ?string $fenAfter,
        public bool $isCheck,
        public bool $isCheckmate,
        public bool $isStalemate,
        public array $legalMoves,
    ) {
    }

    /**
     * @param list<string> $legalMoves
     */
    public static function illegal(array $legalMoves): self
    {
        return new self(false, 'invalid_move', null, false, false, false, $legalMoves);
    }

    /**
     * @param list<string> $legalMoves
     */
    public static function legal(
        string $status,
        string $fenAfter,
        bool $isCheck,
        bool $isCheckmate,
        bool $isStalemate,
        array $legalMoves,
    ): self {
        return new self(true, $status, $fenAfter, $isCheck, $isCheckmate, $isStalemate, $legalMoves);
    }
}
