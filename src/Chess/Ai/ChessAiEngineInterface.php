<?php

declare(strict_types=1);

namespace App\Chess\Ai;

interface ChessAiEngineInterface
{
    public function generateMove(string $fen): AiMoveResult;
}
