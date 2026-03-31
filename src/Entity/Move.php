<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\PromotionPiece;
use App\Repository\MoveRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MoveRepository::class)]
#[ORM\Table(name: 'moves')]
#[ORM\Index(columns: ['game_id'], name: 'idx_moves_game_id')]
#[ORM\Index(columns: ['created_at'], name: 'idx_moves_created_at')]
#[ORM\Index(columns: ['ply'], name: 'idx_moves_ply')]
class Move
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Game::class, inversedBy: 'moves')]
    #[ORM\JoinColumn(name: 'game_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Game $game;

    #[ORM\Column(type: Types::INTEGER)]
    private int $ply;

    #[ORM\Column(type: Types::STRING, length: 5)]
    private string $uci;

    #[ORM\Column(type: Types::STRING, length: 1, enumType: PromotionPiece::class, nullable: true)]
    private ?PromotionPiece $promotion;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $san;

    #[ORM\Column(name: 'fen_after', type: Types::STRING, length: 120)]
    private string $fenAfter;

    #[ORM\Column(name: 'is_check', type: Types::BOOLEAN)]
    private bool $isCheck;

    #[ORM\Column(name: 'is_checkmate', type: Types::BOOLEAN)]
    private bool $isCheckmate;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Game $game,
        int $ply,
        string $uci,
        string $fenAfter,
        ?PromotionPiece $promotion = null,
        ?string $san = null,
        bool $isCheck = false,
        bool $isCheckmate = false,
    ) {
        $this->id = self::generateUuidV4();
        $this->game = $game;
        $this->ply = $ply;
        $this->uci = $uci;
        $this->fenAfter = $fenAfter;
        $this->promotion = $promotion;
        $this->san = $san;
        $this->isCheck = $isCheck;
        $this->isCheckmate = $isCheckmate;
        $this->createdAt = new \DateTimeImmutable();
        $this->game->addMove($this);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    public function getPly(): int
    {
        return $this->ply;
    }

    public function getUci(): string
    {
        return $this->uci;
    }

    public function getPromotion(): ?PromotionPiece
    {
        return $this->promotion;
    }

    public function getSan(): ?string
    {
        return $this->san;
    }

    public function getFenAfter(): string
    {
        return $this->fenAfter;
    }

    public function isCheck(): bool
    {
        return $this->isCheck;
    }

    public function isCheckmate(): bool
    {
        return $this->isCheckmate;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    private static function generateUuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
