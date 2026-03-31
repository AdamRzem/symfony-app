<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum PromotionPiece: string
{
    case Queen = 'q';
    case Rook = 'r';
    case Bishop = 'b';
    case Knight = 'n';
}
