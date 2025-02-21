<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Factory;

use Derafu\Http\Contract\HttpExceptionInterface;
use Derafu\Http\Contract\ProblemDetailInterface;
use Derafu\Http\Contract\ProblemFactoryInterface;
use Derafu\Http\Contract\RequestInterface;
use Derafu\Http\Contract\SafeThrowableFactoryInterface;
use Derafu\Http\Enum\HttpStatus;
use Derafu\Http\Exception\DispatcherException;
use Derafu\Http\ValueObject\ProblemDetail;
use Derafu\Routing\Exception\RouteNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Throwable;

/**
 * Factory for creating Error value objects.
 *
 * This factory creates Error instances by extracting and organizing all
 * relevant information from:
 *
 *   - The exception that occurred.
 *   - The request being processed.
 *   - The application environment.
 *
 * It handles:
 *
 *   - HTTP status code resolution.
 *   - Environment parameter access.
 *   - Debug mode detection.
 */
class ProblemFactory implements ProblemFactoryInterface
{
    /**
     * Creates a new error factory.
     *
     * @param ParameterBagInterface $params For accessing environment settings.
     */
    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly SafeThrowableFactoryInterface $safeThrowableFactory
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function create(
        Throwable $throwable,
        RequestInterface $request
    ): ProblemDetailInterface {
        return new ProblemDetail(
            // Data for RFC 7807.
            type: $throwable instanceof HttpExceptionInterface
                ? $throwable->getUriReference()
                : 'about:blank',
            title: $throwable instanceof HttpExceptionInterface
                ? $throwable->getTitle()
                : null,
            httpStatus: $this->resolveHttpStatus($throwable),
            detail: $throwable->getMessage(),
            request: $request,

            // Data of throwable in a safe way.
            throwable: $this->safeThrowableFactory->create($throwable),

            // Additional context.
            context: $throwable instanceof HttpExceptionInterface
                ? $throwable->getContext()
                : [],

            // Environment info.
            timestamp: date('c'),
            environment: $this->params->get('kernel.environment'),
            debug: $this->params->get('kernel.debug'),
        );
    }

    /**
     * Resolves the appropriate HTTP status for the error.
     *
     * Uses this priority:
     *
     *   1. HTTP status of throwable if implements HttpExceptionInterface.
     *   1. Throwable code if it's a valid HTTP status.
     *   2. Status based on throwable class.
     *   3. Default to unknown error.
     *
     * @param Throwable $throwable The throwable to analyze.
     * @return HttpStatus The resolved HTTP status.
     */
    private function resolveHttpStatus(Throwable $throwable): HttpStatus
    {
        if ($throwable instanceof HttpExceptionInterface) {
            return $throwable->getStatus();
        }

        $status = HttpStatus::tryFrom((int) $throwable->getCode());
        if ($status !== null) {
            return $status;
        }

        return match(get_class($throwable)) {
            RouteNotFoundException::class => HttpStatus::NOT_FOUND,
            DispatcherException::class => HttpStatus::INTERNAL_SERVER_ERROR,
            default => HttpStatus::INTERNAL_SERVER_ERROR,
        };
    }
}
