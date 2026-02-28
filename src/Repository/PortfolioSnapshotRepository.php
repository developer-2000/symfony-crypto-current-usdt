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
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PortfolioSnapshot::class);
    }

    /**
     * Снапшоты за период для API history. Сортировка по calculated_at ASC.
     *
     * @return list<PortfolioSnapshot>
     */
    public function findForHistory(?\DateTimeInterface $from, ?\DateTimeInterface $to, ?int $hours): array
    {
        $qb = $this->createQueryBuilder('s')
            ->orderBy('s.calculatedAt', 'ASC');

        if ($hours !== null) {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $from = $now->modify("-{$hours} hours");
            $to = $now;
        }

        if ($from !== null) {
            $qb->andWhere('s.calculatedAt >= :from')
                ->setParameter('from', $from);
        }
        if ($to !== null) {
            $qb->andWhere('s.calculatedAt <= :to')
                ->setParameter('to', $to);
        }

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
