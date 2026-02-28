<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PortfolioSnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PortfolioSnapshot>
 */
class PortfolioSnapshotRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly int $maxHours,
    ) {
        parent::__construct($registry, PortfolioSnapshot::class);
    }

    /**
     * Snapshots for API history period. One of hours or minutes is set.
     * Ordered by calculated_at ASC. Max rows maxHours * 60.
     *
     * @return list<PortfolioSnapshot>
     */
    public function findForHistory(?int $hours, ?int $minutes): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($minutes !== null) {
            $from = $now->modify("-{$minutes} minutes");
            $to = $now;
        } else {
            $from = $now->modify("-{$hours} hours");
            $to = $now;
        }

        $qb = $this->createQueryBuilder('s')
            ->where('s.calculatedAt >= :from')
            ->andWhere('s.calculatedAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('s.calculatedAt', 'ASC')
            ->setMaxResults($this->maxHours * 60);

        return $qb->getQuery()->getResult();
    }

    public function findLatest(): ?PortfolioSnapshot
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.calculatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
