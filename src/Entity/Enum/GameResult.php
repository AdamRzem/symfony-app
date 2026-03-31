<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum GameResult: string
{
    case Ongoing = 'ongoing';
    case WhiteWin = 'white_win';
    case BlackWin = 'black_win';
    case Draw = 'draw';
}
