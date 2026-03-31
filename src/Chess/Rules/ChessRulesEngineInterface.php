<?php

declare(strict_types=1);

namespace App\Chess\Rules;

interface ChessRulesEngineInterface
{
    public function validateMove(string $fen, string $uciMove): MoveValidationResult;
}
