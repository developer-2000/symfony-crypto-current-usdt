<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\Binance\BinancePriceServiceInterface;

/**
 * Computes portfolio breakdown from current prices: amounts, prices, breakdown_usdt, total_usdt.
 * Used by GET /api/portfolio/breakdown.
 */
final class PortfolioBreakdownService
{
    /** @param array{btc: int|float, eth: int|float, sol: int|float, usdt: int|float} $amounts */
    public function __construct(
        private readonly BinancePriceServiceInterface $binancePriceService,
        private readonly PortfolioTotalCalculator $totalCalculator,
        private readonly array $amounts,
        private readonly array $symbols,
    ) {
    }

    /**
     * Returns portfolio breakdown: amounts, prices, breakdown_usdt, total_usdt, formula.
     *
     * @return array{formula: string, amounts: array<string, float>, prices: array<string, float>, breakdown_usdt: array<string, float>, total_usdt: float}
     * @throws \Throwable on Binance API error
     */
    public function getBreakdown(): array
    {
        $prices = $this->binancePriceService->getAvgPrices($this->symbols);
        $result = $this->totalCalculator->calculate($this->amounts, $prices);

        $bu = $result['breakdown_usdt'];

        return [
            'formula' => PortfolioTotalCalculator::FORMULA,
            'amounts' => [
                'btc' => (float) $this->amounts['btc'],
                'eth' => (float) $this->amounts['eth'],
                'sol' => (float) $this->amounts['sol'],
                'usdt' => (float) $this->amounts['usdt'],
            ],
            'prices' => [
                'BTCUSDT' => (float) ($prices['BTCUSDT'] ?? 0),
                'ETHUSDT' => (float) ($prices['ETHUSDT'] ?? 0),
                'SOLUSDT' => (float) ($prices['SOLUSDT'] ?? 0),
            ],
            'breakdown_usdt' => [
                'btc' => round($bu['btc'], 2),
                'eth' => round($bu['eth'], 2),
                'sol' => round($bu['sol'], 2),
                'usdt' => round($bu['usdt'], 2),
            ],
            'total_usdt' => round($result['total_usdt'], 2),
        ];
    }
}
