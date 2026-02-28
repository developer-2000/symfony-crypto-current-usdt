<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Binance\BinancePriceServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Возвращает расшифровку текущей стоимости портфеля по формуле:
 * total_usdt = BTC_amount×BTC_price + ETH_amount×ETH_price + SOL_amount×SOL_price + USDT_amount.
 */
#[Route('/portfolio/breakdown', name: 'api_portfolio_breakdown_', methods: ['GET'])]
final class PortfolioBreakdownController extends AbstractController
{
    /** @param array{btc: int|float, eth: int|float, sol: int|float, usdt: int|float} $amounts */
    public function __construct(
        private readonly BinancePriceServiceInterface $binancePriceService,
        private readonly array $amounts,
        private readonly array $symbols,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        try {
            $prices = $this->binancePriceService->getAvgPrices($this->symbols);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $btcUsdt = (float) ($prices['BTCUSDT'] ?? 0);
        $ethUsdt = (float) ($prices['ETHUSDT'] ?? 0);
        $solUsdt = (float) ($prices['SOLUSDT'] ?? 0);

        $breakdownBtc = (float) $this->amounts['btc'] * $btcUsdt;
        $breakdownEth = (float) $this->amounts['eth'] * $ethUsdt;
        $breakdownSol = (float) $this->amounts['sol'] * $solUsdt;
        $breakdownUsdt = (float) $this->amounts['usdt'];

        $totalUsdt = $breakdownBtc + $breakdownEth + $breakdownSol + $breakdownUsdt;

        return $this->json([
            'formula' => 'total_usdt = BTC_amount×BTC_price + ETH_amount×ETH_price + SOL_amount×SOL_price + USDT_amount',
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
        ], Response::HTTP_OK, [], ['json_encode_options' => \JSON_UNESCAPED_SLASHES]);
    }
}
