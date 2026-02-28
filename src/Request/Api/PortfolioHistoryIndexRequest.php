<?php

declare(strict_types=1);

namespace App\Request\Api;

use App\Exception\RequestValidationException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Input and validation for GET /api/portfolio/history.
 * Resolves hours, minutes or from/to into normalized from, to, hours.
 */
final class PortfolioHistoryIndexRequest
{
    private ?\DateTimeInterface $resolvedFrom = null;
    private ?\DateTimeInterface $resolvedTo = null;
    private ?int $resolvedHours = null;

    public static function fromRequest(Request $request, int $maxHours, int $defaultHours): self
    {
        $self = new self();
        $hours = $request->query->get('hours');
        $minutes = $request->query->get('minutes');
        $fromStr = $request->query->get('from');
        $toStr = $request->query->get('to');

        if ($fromStr !== null && $toStr !== null) {
            try {
                $self->resolvedFrom = new \DateTimeImmutable($fromStr, new \DateTimeZone('UTC'));
                $self->resolvedTo = new \DateTimeImmutable($toStr, new \DateTimeZone('UTC'));
            } catch (\Throwable) {
                throw new RequestValidationException('Parameters from and to must be dates in ISO 8601 format.');
            }
            if ($self->resolvedFrom > $self->resolvedTo) {
                throw new RequestValidationException('Parameter from cannot be after to.');
            }
            return $self;
        }

        if ($minutes !== null && $hours === null && $fromStr === null && $toStr === null) {
            $minutesInt = filter_var($minutes, \FILTER_VALIDATE_INT);
            $maxMinutes = $maxHours * 60;
            if ($minutesInt === false || $minutesInt < 1 || $minutesInt > $maxMinutes) {
                throw new RequestValidationException(
                    sprintf('Parameter minutes must be an integer from 1 to %d.', $maxMinutes)
                );
            }
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $self->resolvedTo = $now;
            $self->resolvedFrom = $now->modify("-{$minutesInt} minutes");
            return $self;
        }

        if ($hours !== null && $fromStr === null && $toStr === null) {
            $hoursInt = filter_var($hours, \FILTER_VALIDATE_INT);
            if ($hoursInt === false || $hoursInt < 1 || $hoursInt > $maxHours) {
                throw new RequestValidationException(
                    sprintf('Parameter hours must be an integer from 1 to %d.', $maxHours)
                );
            }
            $self->resolvedHours = $hoursInt;
            return $self;
        }

        if ($hours === null && $fromStr === null && $toStr === null) {
            $self->resolvedHours = $defaultHours;
            return $self;
        }

        throw new RequestValidationException('Provide hours, minutes, or both from and to.');
    }

    public function getResolvedFrom(): ?\DateTimeInterface
    {
        return $this->resolvedFrom;
    }

    public function getResolvedTo(): ?\DateTimeInterface
    {
        return $this->resolvedTo;
    }

    public function getResolvedHours(): ?int
    {
        return $this->resolvedHours;
    }
}
