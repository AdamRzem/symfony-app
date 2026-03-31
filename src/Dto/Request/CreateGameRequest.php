<?php

declare(strict_types=1);

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateGameRequest
{
    public function __construct(
        #[Assert\Choice(choices: ['white', 'black'])]
        public ?string $aiColor = null,
    ) {
    }
}
