<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PortfolioSnapshotRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PortfolioSnapshotRepository::class)]
#[ORM\Table(name: 'portfolio_snapshot')]
#[ORM\UniqueConstraint(name: 'uniq_portfolio_snapshot_calculated_at', columns: ['calculated_at'])]
#[ORM\Index(name: 'idx_portfolio_snapshot_calculated_at', columns: ['calculated_at'])]
class PortfolioSnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'calculated_at')]
    private \DateTimeImmutable $calculatedAt;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 8, name: 'amount_usdt')]
    private string $amountUsdt;

    public function __construct(\DateTimeImmutable $calculatedAt, string $amountUsdt)
    {
        $this->calculatedAt = $calculatedAt;
        $this->amountUsdt = $amountUsdt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCalculatedAt(): \DateTimeImmutable
    {
        return $this->calculatedAt;
    }

    public function getAmountUsdt(): string
    {
        return $this->amountUsdt;
    }
}
