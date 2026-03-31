<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum GameStatus: string
{
    case InProgress = 'in_progress';
    case Check = 'check';
    case Checkmate = 'checkmate';
    case Stalemate = 'stalemate';
    case Draw = 'draw';
    case Resigned = 'resigned';
}
