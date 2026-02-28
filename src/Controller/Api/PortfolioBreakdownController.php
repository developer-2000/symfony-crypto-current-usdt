<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\PortfolioBreakdownService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Returns current portfolio breakdown by formula:
 * total_usdt = BTC_amount×BTC_price + ETH_amount×ETH_price + SOL_amount×SOL_price + USDT_amount.
 */
#[Route('/portfolio/breakdown', name: 'api_portfolio_breakdown_', methods: ['GET'])]
final class PortfolioBreakdownController extends AbstractController
{
    public function __construct(
        private readonly PortfolioBreakdownService $breakdownService,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        try {
            $data = $this->breakdownService->getBreakdown();
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->json($data, Response::HTTP_OK, [], ['json_encode_options' => \JSON_UNESCAPED_SLASHES]);
    }
}
