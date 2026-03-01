<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Single place for portfolio total_usdt formula (Binance avgPrice + hourly snapshot — float is acceptable).
 *
 * @param array{btc: int|float, eth: int|float, sol: int|float, usdt: int|float} $amounts
 * @param array{BTCUSDT?: float, ETHUSDT?: float, SOLUSDT?: float} $prices
 *
 * @return array{breakdown_usdt: array{btc: float, eth: float, sol: float, usdt: float}, total_usdt: float}
 */
final class PortfolioTotalCalculator
{
    public const FORMULA = 'total_usdt = BTC_amount×BTC_price + ETH_amount×ETH_price + SOL_amount×SOL_price + USDT_amount';

    public function calculate(array $amounts, array $prices): array
    {
        $btcUsdt = (float) ($prices['BTCUSDT'] ?? 0);
        $ethUsdt = (float) ($prices['ETHUSDT'] ?? 0);
        $solUsdt = (float) ($prices['SOLUSDT'] ?? 0);

        $breakdownBtc = (float) $amounts['btc'] * $btcUsdt;
        $breakdownEth = (float) $amounts['eth'] * $ethUsdt;
        $breakdownSol = (float) $amounts['sol'] * $solUsdt;
        $breakdownUsdt = (float) $amounts['usdt'];

        $totalUsdt = $breakdownBtc + $breakdownEth + $breakdownSol + $breakdownUsdt;

        return [
            'breakdown_usdt' => [
                'btc' => $breakdownBtc,
                'eth' => $breakdownEth,
                'sol' => $breakdownSol,
                'usdt' => $breakdownUsdt,
            ],
            'total_usdt' => $totalUsdt,
        ];
    }
}
