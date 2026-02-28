<?php

declare(strict_types=1);

namespace App\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Marker for request classes with validation before controller (Laravel FormRequest style).
 * Resolver creates DTO via create(), then calls validate(); throws RequestValidationException on error.
 */
interface FormRequestInterface
{
    public static function create(ContainerInterface $container, Request $request): self;

    public function validate(): void;
}
