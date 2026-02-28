<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PortfolioSnapshot;
use App\Service\Binance\BinancePriceServiceInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final class PortfolioValuationService
{
    /** @param array{btc: int|float, eth: int|float, sol: int|float, usdt: int|float} $amounts */
    public function __construct(
        private readonly BinancePriceServiceInterface $binancePriceService,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $snapshotGranularity,
        private readonly int $amountUsdtScale,
        private readonly array $amounts,
        private readonly array $symbols,
    ) {
    }

    public function getSnapshotGranularity(): string
    {
        return $this->snapshotGranularity;
    }

    /**
     * Рассчитывает стоимость портфеля и сохраняет снапшот за текущий слот (час или секунда по granularity).
     * Идемпотентно: при уже существующей записи за этот час дубликат не создаётся.
     * Возвращает данные сохранённого снапшота для публикации в Mercure или null при дубликате.
     *
     * @return array{calculated_at: string, amount_usdt: float}|null
     * @throws \App\Exception\BinanceApiException
     */
    public function snapshot(): ?array
    {
        $prices = $this->binancePriceService->getAvgPrices($this->symbols);

        $total = (float) $this->amounts['usdt'];
        $total += (float) $this->amounts['btc'] * ($prices['BTCUSDT'] ?? 0);
        $total += (float) $this->amounts['eth'] * ($prices['ETHUSDT'] ?? 0);
        $total += (float) $this->amounts['sol'] * ($prices['SOLUSDT'] ?? 0);

        $amountUsdt = number_format((float) $total, $this->amountUsdtScale, '.', '');
        $calculatedAt = $this->currentSlotUtc();
        $snapshot = new PortfolioSnapshot($calculatedAt, $amountUsdt);

        try {
            $this->entityManager->persist($snapshot);
            $this->entityManager->flush();
            return [
                'calculated_at' => $calculatedAt->format('Y-m-d\TH:i:sP'),
                'amount_usdt' => (float) $amountUsdt,
            ];
        } catch (UniqueConstraintViolationException $e) {
            $this->entityManager->clear();
            return null;
        }
    }

    private function currentSlotUtc(): \DateTimeImmutable
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($this->snapshotGranularity === 'second') {
            $s = (int) $now->format('s');

            return $now->setTime((int) $now->format('H'), (int) $now->format('i'), $s, 0);
        }
        if ($this->snapshotGranularity === 'minute') {
            return $now->setTime((int) $now->format('H'), (int) $now->format('i'), 0, 0);
        }
        $h = (int) $now->format('H');

        return $now->setTime($h, 0, 0);
    }
}
