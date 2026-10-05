<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
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
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
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
 *   - Translation of the message (the `detail`) and the title of the problem.
 *   - Environment parameter access.
 *   - Debug mode detection.
 */
class ProblemFactory implements ProblemFactoryInterface
{
    /**
     * Creates a new error factory.
     *
     * @param ParameterBagInterface $params For accessing environment settings.
     * @param SafeThrowableFactoryInterface $safeThrowableFactory
     * @param TranslatorInterface|null $translator Translates the message of
     * the throwables that can be translated. Without it, the message of the
     * throwable is used as it is.
     */
    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly SafeThrowableFactoryInterface $safeThrowableFactory,
        private readonly ?TranslatorInterface $translator = null
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function create(
        Throwable $throwable,
        RequestInterface $request
    ): ProblemDetailInterface {
        $httpStatus = $this->resolveHttpStatus($throwable);

        return new ProblemDetail(
            // Data for RFC 7807.
            type: $throwable instanceof HttpExceptionInterface
                ? $throwable->getUriReference()
                : 'about:blank',
            title: $this->translate(
                $throwable instanceof HttpExceptionInterface
                    ? $throwable->getTitle()
                    : $httpStatus->getReasonPhrase()
            ),
            httpStatus: $httpStatus,
            detail: $this->resolveDetail($throwable),
            request: $request,

            // Data of throwable in a safe way.
            throwable: $this->safeThrowableFactory->create($throwable),

            // Additional context.
            context: $throwable instanceof HttpExceptionInterface
                ? $throwable->getContext()
                : [],

            // Additional headers.
            headers: $throwable instanceof HttpExceptionInterface
                ? $throwable->getHeaders()
                : [],

            // Environment info.
            timestamp: date('c'),
            environment: $this->params->get('kernel.environment'),
            debug: $this->params->get('kernel.debug'),
        );
    }

    /**
     * Translates a text with the translator, if there is one.
     *
     * The text is its own translation key, in the `errors` domain (the one the
     * messages of the exceptions use). A text without translation is kept as
     * it is.
     *
     * @param string $text The text to translate.
     * @return string The translated text.
     */
    private function translate(string $text): string
    {
        return $this->translator?->trans($text, [], 'errors') ?? $text;
    }

    /**
     * Resolves the detail of the problem: the message of the throwable.
     *
     * If the throwable can be translated, the message is translated with the
     * translator. `trans()` only exists on translatable throwables, so it is
     * never called on any other one. If the translation fails the error is not
     * hidden: it is left to whoever handles the failure.
     *
     * @param Throwable $throwable The throwable to analyze.
     * @return string The detail of the problem.
     */
    private function resolveDetail(Throwable $throwable): string
    {
        if ($this->translator === null || !$throwable instanceof TranslatableInterface) {
            return $throwable->getMessage();
        }

        return $throwable->trans($this->translator);
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
