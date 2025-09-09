<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Http\Exception;

use Derafu\Http\Contract\HttpExceptionInterface;
use Derafu\Http\Enum\HttpStatus;
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Exception\Core\TranslatableRuntimeException;
use Throwable;

/**
 * Exception for too many requests.
 */
class TooManyRequestsException extends TranslatableRuntimeException implements HttpExceptionInterface
{
    /**
     * Creates a new too many requests exception.
     *
     * @param string|array|TranslatableInterface $message The exception message:
     *   - string: Will be used as both message and translation key.
     *   - array: First element must be string (message), remaining elements are
     *     parameters.
     *   - TranslatableInterface: Will be used directly.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous throwable used for exception
     * chaining.
     * @param array $headers Additional headers to add to the HTTP response.
     */
    public function __construct(
        string|array|TranslatableInterface $message = 'Too many requests.',
        int $code = 0,
        ?Throwable $previous = null,
        private readonly array $headers = []
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * {@inheritDoc}
     */
    public function getUriReference(): string
    {
        return 'https://tools.ietf.org/html/rfc6585#section-4';
    }

    /**
     * {@inheritDoc}
     */
    public function getTitle(): string
    {
        return 'Too Many Requests';
    }

    /**
     * {@inheritDoc}
     */
    public function getStatus(): HttpStatus
    {
        return HttpStatus::TOO_MANY_REQUESTS;
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
        return $this->headers;
    }
}
