<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\PortfolioSnapshotRepository;
use App\Request\Api\PortfolioHistoryRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portfolio/history', name: 'api_portfolio_history_', methods: ['GET'])]
final class PortfolioHistoryController extends AbstractController
{
    public function __construct(
        private readonly PortfolioSnapshotRepository $repository,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(PortfolioHistoryRequest $request): JsonResponse
    {
        $snapshots = $this->repository->findForHistory($request->getHours(), $request->getMinutes());

        $data = array_map(
            fn ($s) => [
                'time' => $s->getCalculatedAt()->format('Y-m-d\TH:i:sP'),
                'amount_usdt' => (float) $s->getAmountUsdt(),
            ],
            $snapshots
        );

        $response = $this->json($data, Response::HTTP_OK, [], ['json_encode_options' => \JSON_UNESCAPED_SLASHES]);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');

        return $response;
    }
}
