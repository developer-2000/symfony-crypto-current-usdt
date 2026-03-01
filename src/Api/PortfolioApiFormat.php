<?php

declare(strict_types=1);

namespace App\Api;

/**
 * Format strings for portfolio API responses (ISO 8601 date-time with timezone).
 */
final class PortfolioApiFormat
{
    public const DATE_TIME_ISO8601 = 'Y-m-d\TH:i:sP';
}
