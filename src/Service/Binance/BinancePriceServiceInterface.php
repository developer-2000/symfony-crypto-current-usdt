<?php

declare(strict_types=1);

namespace App\Service\Binance;

/**
 * Returns average prices by symbol (symbol => price as float).
 *
 * @return array<string, float>
 */
interface BinancePriceServiceInterface
{
    /**
     * @param list<string> $symbols e.g. ['BTCUSDT', 'ETHUSDT']
     * @return array<string, float> symbol => price
     *
     * @throws \App\Exception\BinanceApiException
     */
    public function getAvgPrices(array $symbols): array;
}
