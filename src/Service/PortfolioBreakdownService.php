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
    private const FORMULA = 'total_usdt = BTC_amount×BTC_price + ETH_amount×ETH_price + SOL_amount×SOL_price + USDT_amount';

    /** @param array{btc: int|float, eth: int|float, sol: int|float, usdt: int|float} $amounts */
    public function __construct(
        private readonly BinancePriceServiceInterface $binancePriceService,
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

        $btcUsdt = (float) ($prices['BTCUSDT'] ?? 0);
        $ethUsdt = (float) ($prices['ETHUSDT'] ?? 0);
        $solUsdt = (float) ($prices['SOLUSDT'] ?? 0);

        $breakdownBtc = (float) $this->amounts['btc'] * $btcUsdt;
        $breakdownEth = (float) $this->amounts['eth'] * $ethUsdt;
        $breakdownSol = (float) $this->amounts['sol'] * $solUsdt;
        $breakdownUsdt = (float) $this->amounts['usdt'];

        $totalUsdt = $breakdownBtc + $breakdownEth + $breakdownSol + $breakdownUsdt;

        return [
            'formula' => self::FORMULA,
            'amounts' => [
                'btc' => (float) $this->amounts['btc'],
                'eth' => (float) $this->amounts['eth'],
                'sol' => (float) $this->amounts['sol'],
                'usdt' => (float) $this->amounts['usdt'],
            ],
            'prices' => [
                'BTCUSDT' => $btcUsdt,
                'ETHUSDT' => $ethUsdt,
                'SOLUSDT' => $solUsdt,
            ],
            'breakdown_usdt' => [
                'btc' => round($breakdownBtc, 2),
                'eth' => round($breakdownEth, 2),
                'sol' => round($breakdownSol, 2),
                'usdt' => round($breakdownUsdt, 2),
            ],
            'total_usdt' => round($totalUsdt, 2),
        ];
    }
}
