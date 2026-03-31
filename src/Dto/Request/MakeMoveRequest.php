<?php

declare(strict_types=1);

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MakeMoveRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[a-h][1-8][a-h][1-8][qrbn]?$/')]
        public string $uciMove,
    ) {
    }
}
