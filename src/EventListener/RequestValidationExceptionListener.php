<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\RequestValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Converts RequestValidationException to 422 response with JSON { "error": "..." }.
 */
final class RequestValidationExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        if (!$throwable instanceof RequestValidationException) {
            return;
        }

        $code = $throwable->getCode();
        if (!is_int($code) || $code < 400) {
            $code = Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        $data = ['error' => $throwable->getMessage()];
        $event->setResponse(new JsonResponse($data, $code));
    }
}
