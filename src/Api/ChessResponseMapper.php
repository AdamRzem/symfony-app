<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\Game;
use App\Entity\Move;

final class ChessResponseMapper
{
    /**
     * @return array<string, mixed>
     */
    public function game(Game $game): array
    {
        $moves = $game->getMoves();
        $lastMove = $moves->isEmpty() ? null : $moves->last();

        return [
            'id' => $game->getId(),
            'fen' => $game->getFen(),
            'turn' => $game->getTurn()->value,
            'status' => $game->getStatus()->value,
            'result' => $game->getResult()->value,
            'aiColor' => $game->getAiColor()->value,
            'createdAt' => $game->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $game->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'lastMove' => $lastMove instanceof Move ? $this->move($lastMove) : null,
        ];
    }

    /**
     * @param list<Move> $moves
     *
     * @return array{moves: list<array<string, mixed>>}
     */
    public function moveList(array $moves): array
    {
        return [
            'moves' => array_map($this->move(...), $moves),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function move(Move $move): array
    {
        return [
            'id' => $move->getId(),
            'ply' => $move->getPly(),
            'uci' => $move->getUci(),
            'promotion' => $move->getPromotion()?->value,
            'san' => $move->getSan(),
            'fenAfter' => $move->getFenAfter(),
            'isCheck' => $move->isCheck(),
            'isCheckmate' => $move->isCheckmate(),
            'createdAt' => $move->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
