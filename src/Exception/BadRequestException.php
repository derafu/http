<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Exception;

use Derafu\Http\Contract\HttpExceptionInterface;
use Derafu\Http\Enum\HttpStatus;
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Throwable;

/**
 * Exception for a request that can not be attended because of what the client
 * sent (a field that is missing, a file that is not there).
 *
 * Mapped to 400 Bad Request instead of the generic 500: the failure is in the
 * request, not in the server.
 *
 * It is an invalid argument, so the code that does not know about HTTP (a
 * service that validates what it receives) can throw it and the callers that
 * already catch the invalid argument keep working.
 */
class BadRequestException extends TranslatableInvalidArgumentException implements HttpExceptionInterface
{
    /**
     * Creates a new bad request exception.
     *
     * @param string|array|TranslatableInterface $message The exception message:
     *   - string: Will be used as both message and translation key.
     *   - array: First element must be string (message), remaining elements are
     *     parameters.
     *   - TranslatableInterface: Will be used directly.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous throwable used for exception
     * chaining.
     */
    public function __construct(
        string|array|TranslatableInterface $message = 'Bad request.',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * {@inheritDoc}
     */
    public function getUriReference(): string
    {
        return 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/400';
    }

    /**
     * {@inheritDoc}
     */
    public function getTitle(): string
    {
        return 'Bad Request';
    }

    /**
     * {@inheritDoc}
     */
    public function getStatus(): HttpStatus
    {
        return HttpStatus::BAD_REQUEST;
    }

    /**
     * {@inheritDoc}
     */
    public function getContext(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getHeaders(): array
    {
        return [];
    }
}
