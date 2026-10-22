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
 * Exception for a request that is well formed but whose data is not valid (422): for example, a RUT with a wrong verification digit. The errors of each field can go in the context.
 */
class UnprocessableContentException extends HttpException
{
    /**
     * Creates a new exception.
     *
     * @param string|array<int|string, mixed>|TranslatableInterface $message The exception message.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous throwable used for exception
     * chaining.
     * @param array<string,string|list<string>> $headers Additional headers to
     * add to the HTTP response.
     * @param array<string, mixed> $context Additional context of the problem.
     */
    public function __construct(
        string|array|TranslatableInterface $message = 'Unprocessable content.',
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
        return HttpStatus::UNPROCESSABLE_CONTENT;
    }
}
