<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\GameResult;
use App\Entity\Enum\GameStatus;
use App\Entity\Enum\Side;
use App\Repository\GameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameRepository::class)]
#[ORM\Table(name: 'games')]
#[ORM\Index(columns: ['status'], name: 'idx_games_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_games_created_at')]
class Game
{
    public const INITIAL_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 120)]
    private string $fen;

    #[ORM\Column(type: Types::STRING, length: 5, enumType: Side::class)]
    private Side $turn;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: GameStatus::class)]
    private GameStatus $status;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: GameResult::class)]
    private GameResult $result;

    #[ORM\Column(name: 'ai_color', type: Types::STRING, length: 5, enumType: Side::class)]
    private Side $aiColor;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, Move>
     */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: Move::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['ply' => 'ASC'])]
    private Collection $moves;

    public function __construct(Side $aiColor, string $fen = self::INITIAL_FEN)
    {
        $this->id = self::generateUuidV4();
        $this->fen = $fen;
        $this->turn = Side::White;
        $this->status = GameStatus::InProgress;
        $this->result = GameResult::Ongoing;
        $this->aiColor = $aiColor;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->moves = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getFen(): string
    {
        return $this->fen;
    }

    public function setFen(string $fen): self
    {
        $this->fen = $fen;

        return $this;
    }

    public function getTurn(): Side
    {
        return $this->turn;
    }

    public function setTurn(Side $turn): self
    {
        $this->turn = $turn;

        return $this;
    }

    public function getStatus(): GameStatus
    {
        return $this->status;
    }

    public function setStatus(GameStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getResult(): GameResult
    {
        return $this->result;
    }

    public function setResult(GameResult $result): self
    {
        $this->result = $result;

        return $this;
    }

    public function getAiColor(): Side
    {
        return $this->aiColor;
    }

    public function setAiColor(Side $aiColor): self
    {
        $this->aiColor = $aiColor;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * @return Collection<int, Move>
     */
    public function getMoves(): Collection
    {
        return $this->moves;
    }

    public function addMove(Move $move): self
    {
        if (!$this->moves->contains($move)) {
            $this->moves->add($move);
            $this->touch();
        }

        return $this;
    }

    public function removeMove(Move $move): self
    {
        if ($this->moves->removeElement($move)) {
            $this->touch();
        }

        return $this;
    }

    private static function generateUuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
