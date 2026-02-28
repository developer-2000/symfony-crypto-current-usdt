<?php

declare(strict_types=1);

namespace App\Service\Binance;

/**
 * Возвращает средние цены по символам (symbol => price in float).
 *
 * @return array<string, float>
 */
interface BinancePriceServiceInterface
{
    /**
     * @param list<string> $symbols например ['BTCUSDT', 'ETHUSDT']
     * @return array<string, float> symbol => price
     *
     * @throws \App\Exception\BinanceApiException
     */
    public function getAvgPrices(array $symbols): array;
}
