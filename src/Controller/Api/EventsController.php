<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\AppLogReader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/events', name: 'api_events_', methods: ['GET'])]
final class EventsController extends AbstractController
{
    public function __construct(
        private readonly AppLogReader $appLogReader,
        private readonly int $defaultLimit,
        private readonly int $maxLimit,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->query->get('limit', $this->defaultLimit);
        if ($limit < 1 || $limit > $this->maxLimit) {
            $limit = $this->defaultLimit;
        }
        $events = $this->appLogReader->getRecentEvents($limit);
        $response = new JsonResponse($events);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');

        return $response;
    }
}
