<?php

declare(strict_types=1);

namespace App\Controller;

use App\Request\FormRequestInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller argument resolver: for type implementing FormRequestInterface,
 * creates DTO from Request and container, calls validate(), returns DTO.
 */
final class FormRequestValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();
        if ($type === null || !is_subclass_of($type, FormRequestInterface::class)) {
            return [];
        }

        $dto = $type::create($this->container, $request);
        $dto->validate();

        return [$dto];
    }
}
