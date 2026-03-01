<?php

declare(strict_types=1);

namespace App\Request\Api;

use App\Exception\RequestValidationException;
use App\Request\FormRequestInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validation for GET /api/portfolio/history. Only ?hours=N or ?minutes=N allowed.
 */
final class PortfolioHistoryRequest implements FormRequestInterface
{
    private ?int $hours = null;
    private ?int $minutes = null;

    private function __construct(
        private readonly Request $request,
        private readonly int $maxHours,
        private readonly int $defaultHours,
    ) {
    }

    public static function create(ContainerInterface $container, Request $request): self
    {
        $maxHours = (int) $container->getParameter('portfolio_history.max_hours');
        $defaultHours = (int) $container->getParameter('portfolio_history.default_hours');
        return new self($request, $maxHours, $defaultHours);
    }

    public function validate(): void
    {
        $hoursRaw = $this->request->query->get('hours');
        $minutesRaw = $this->request->query->get('minutes');
        $maxMinutes = $this->maxHours * 60;

        if ($minutesRaw !== null && $hoursRaw === null) {
            $v = $this->parseBoundedInt($minutesRaw, 1, $maxMinutes);
            if ($v === null) {
                $this->fail(sprintf('Parameter minutes must be an integer from 1 to %d.', $maxMinutes));
            }
            $this->minutes = $v;
            return;
        }

        if ($hoursRaw !== null) {
            $v = $this->parseBoundedInt($hoursRaw, 1, $this->maxHours);
            if ($v === null) {
                $this->fail(sprintf('Parameter hours must be an integer from 1 to %d.', $this->maxHours));
            }
            $this->hours = $v;
            return;
        }

        $this->hours = $this->defaultHours;
    }

    /**
     * Parses query string value as integer within [min, max]. Returns null if missing, not an int or out of range.
     */
    private function parseBoundedInt(?string $raw, int $min, int $max): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $v = filter_var($raw, \FILTER_VALIDATE_INT);
        if ($v === false || $v < $min || $v > $max) {
            return null;
        }
        return $v;
    }

    private function fail(string $message): void
    {
        throw new RequestValidationException($message, ['error' => $message]);
    }

    public function getHours(): ?int
    {
        return $this->hours;
    }

    public function getMinutes(): ?int
    {
        return $this->minutes;
    }
}
