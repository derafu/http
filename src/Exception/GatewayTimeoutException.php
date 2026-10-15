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
 * Exception for a service that this one depends on that did not answer in time (504).
 */
class GatewayTimeoutException extends HttpException
{
    /**
     * Creates a new exception.
     *
     * @param string|array<int|string, mixed>|TranslatableInterface $message The exception message.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous throwable used for exception
     * chaining.
     * @param array $headers Additional headers to
     * add to the HTTP response.
     * @param array<string, mixed> $context Additional context of the problem.
     */
    public function __construct(
        string|array|TranslatableInterface $message = 'Gateway timeout.',
        int $code = 0,
        ?Throwable $previous = null,
        array $headers = [],
        array $context = []
    ) {
        parent::__construct($message, $code, $previous, $headers, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function getStatus(): HttpStatus
    {
        return HttpStatus::GATEWAY_TIMEOUT;
    }
}
