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

use Derafu\Http\Enum\HttpStatus;
use Derafu\Translation\Contract\TranslatableInterface;
use Throwable;

/**
 * Exception for too many requests (429).
 */
class TooManyRequestsException extends HttpException
{
    /**
     * Creates a new too many requests exception.
     *
     * @param string|array<int|string, mixed>|TranslatableInterface $message The exception message:
     *   - string: Will be used as both message and translation key.
     *   - array: First element must be string (message), remaining elements are
     *     parameters.
     *   - TranslatableInterface: Will be used directly.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous throwable used for exception
     * chaining.
     * @param array $headers Additional headers to
     * add to the HTTP response.
     * @param array<string, mixed> $context Additional context of the problem.
     */
    public function __construct(
        string|array|TranslatableInterface $message = 'Too many requests.',
        int $code = 0,
        ?Throwable $previous = null,
        array $headers = [],
        array $context = []
    ) {
        parent::__construct($message, $code, $previous, $headers, $context);
    }

    /**
     * {@inheritDoc}
     *
     * The one of RFC 6585, as it always was.
     */
    public function getUriReference(): string
    {
        return 'https://tools.ietf.org/html/rfc6585#section-4';
    }

    /**
     * {@inheritDoc}
     */
    public function getStatus(): HttpStatus
    {
        return HttpStatus::TOO_MANY_REQUESTS;
    }
}
