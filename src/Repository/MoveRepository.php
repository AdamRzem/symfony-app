<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Move;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Move>
 */
final class MoveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Move::class);
    }

    public function save(Move $move, bool $flush = false): void
    {
        $this->getEntityManager()->persist($move);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Move $move, bool $flush = false): void
    {
        $this->getEntityManager()->remove($move);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return list<Move>
     */
    public function findByGameIdOrderedByPly(string $gameId): array
    {
        return $this->createQueryBuilder('move')
            ->innerJoin('move.game', 'game')
            ->andWhere('game.id = :gameId')
            ->setParameter('gameId', $gameId)
            ->orderBy('move.ply', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
