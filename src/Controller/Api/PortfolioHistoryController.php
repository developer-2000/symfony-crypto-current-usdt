<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\PortfolioSnapshotRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portfolio/history', name: 'api_portfolio_history_', methods: ['GET'])]
final class PortfolioHistoryController extends AbstractController
{
    public function __construct(
        private readonly PortfolioSnapshotRepository $repository,
        private readonly int $maxHours,
        private readonly int $defaultHours,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $hours = $request->query->get('hours');
        $minutes = $request->query->get('minutes');
        $fromStr = $request->query->get('from');
        $toStr = $request->query->get('to');

        $from = null;
        $to = null;
        $hoursInt = null;

        if ($fromStr !== null && $toStr !== null) {
            try {
                $from = new \DateTimeImmutable($fromStr, new \DateTimeZone('UTC'));
                $to = new \DateTimeImmutable($toStr, new \DateTimeZone('UTC'));
            } catch (\Throwable) {
                return $this->json([
                    'error' => 'Параметры from и to должны быть датами в формате ISO 8601.',
                ], Response::HTTP_BAD_REQUEST);
            }
            if ($from > $to) {
                return $this->json([
                    'error' => 'Параметр from не может быть позже to.',
                ], Response::HTTP_BAD_REQUEST);
            }
        } elseif ($minutes !== null && $hours === null && $fromStr === null && $toStr === null) {
            $minutesInt = filter_var($minutes, \FILTER_VALIDATE_INT);
            $maxMinutes = $this->maxHours * 60;
            if ($minutesInt === false || $minutesInt < 1 || $minutesInt > $maxMinutes) {
                return $this->json([
                    'error' => sprintf('Параметр minutes должен быть целым числом от 1 до %d.', $maxMinutes),
                ], Response::HTTP_BAD_REQUEST);
            }
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $to = $now;
            $from = $now->modify("-{$minutesInt} minutes");
        } elseif ($hours !== null && $fromStr === null && $toStr === null) {
            $hoursInt = filter_var($hours, \FILTER_VALIDATE_INT);
            if ($hoursInt === false || $hoursInt < 1 || $hoursInt > $this->maxHours) {
                return $this->json([
                    'error' => sprintf('Параметр hours должен быть целым числом от 1 до %d.', $this->maxHours),
                ], Response::HTTP_BAD_REQUEST);
            }
        } elseif ($hours === null && $fromStr === null && $toStr === null) {
            $hoursInt = $this->defaultHours;
        } else {
            return $this->json([
                'error' => 'Укажите hours, minutes либо оба параметра from и to.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $snapshots = $this->repository->findForHistory($from, $to, $hoursInt);

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
